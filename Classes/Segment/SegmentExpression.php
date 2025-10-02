<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Segment;

/*
 * This code originates from https://github.com/matomo-org/matomo/blob/4.x-dev/core/Segment/SegmentExpression.php
 * and was adjusted to not build SQL queries but PHP string matching expressions.
 */

use Neos\Flow\Annotations as Flow;
use Exception;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;

class SegmentExpression
{
    const AND_DELIMITER = ';';
    const OR_DELIMITER = ',';

    const MATCH_EQUAL = '==';
    const MATCH_NOT_EQUAL = '!=';
    const MATCH_GREATER_OR_EQUAL = '>=';
    const MATCH_LESS_OR_EQUAL = '<=';
    const MATCH_GREATER = '>';
    const MATCH_LESS = '<';
    const MATCH_CONTAINS = '=@';
    const MATCH_DOES_NOT_CONTAIN = '!@';
    const MATCH_STARTS_WITH = '=^';
    const MATCH_ENDS_WITH = '=$';

    const BOOL_OPERATOR_OR = ' || ';
    const BOOL_OPERATOR_AND = ' && ';
    const BOOL_OPERATOR_END = '';

    // Note: you can't write this in the API, but access this feature
    // via field!=        <- IS NOT NULL
    // or via field==     <- IS NULL / empty
    const MATCH_IS_NOT_NULL_NOR_EMPTY = '::NOT_NULL';
    const MATCH_IS_NULL_OR_EMPTY = '::NULL';

    // Special case, since we look up Page URLs/Page titles in a sub SQL query
    const MATCH_ACTIONS_CONTAINS = 'IN';
    const MATCH_ACTIONS_NOT_CONTAINS = 'NOTIN';

    const INDEX_BOOL_OPERATOR = 0;
    const INDEX_OPERAND = 1;

    const INDEX_OPERAND_NAME = 0;
    const INDEX_OPERAND_OPERATOR = 1;
    const INDEX_OPERAND_VALUE = 2;

    const SQL_WHERE_DO_NOT_MATCH_ANY_ROW = "(1 = 0)";
    const SQL_WHERE_MATCHES_ALL_ROWS = "(1 = 1)";

    protected string $segmentDefinitionString = '';
    protected $joins = [];
    protected $valuesBind = [];
    protected $tree = [];
    protected $parsedSubExpressions = [];

    #[Flow\Inject]
    protected LoggerInterface $logger;

    protected array $matomoToAnalyticsFieldMapping = [];

    public function __construct(string $segmentDefinitionString, array $matomoToAnalyticsFieldMapping)
    {
        $this->matomoToAnalyticsFieldMapping = $matomoToAnalyticsFieldMapping;
        $this->segmentDefinitionString = $segmentDefinitionString;
        $this->tree = $this->parseTree();
    }

    public function isEmpty(): bool
    {
        return count($this->tree) === 0;
    }

    public function getSubExpressionCount()
    {
        $cleaned = array_filter($this->parsedSubExpressions, static function ($part) {
            return !empty($part[1][0]);
        });
        return count($cleaned);
    }

    /**
     * Given the array of parsed filters containing, for each filter,
     * the boolean operator (AND/OR) and the operand,
     * Will return the array where the filters are in SQL representation
     *
     * @return array
     * @throws Exception
     */
    public function parseSubExpressions(): array
    {
        $parsedSubExpressions = [];
        foreach ($this->tree as $leaf) {
            $operand = $leaf[self::INDEX_OPERAND];

            $operand = urldecode($operand);

            $operator = $leaf[self::INDEX_BOOL_OPERATOR];
            $pattern = '/^(.+?)(' . self::MATCH_EQUAL . '|'
                . self::MATCH_NOT_EQUAL . '|'
                . self::MATCH_GREATER_OR_EQUAL . '|'
                . self::MATCH_GREATER . '|'
                . self::MATCH_LESS_OR_EQUAL . '|'
                . self::MATCH_LESS . '|'
                . self::MATCH_CONTAINS . '|'
                . self::MATCH_DOES_NOT_CONTAIN . '|'
                . preg_quote(self::MATCH_STARTS_WITH) . '|'
                . preg_quote(self::MATCH_ENDS_WITH)
                . '){1}(.*)/';
            $match = preg_match($pattern, $operand, $matches);
            if ((int)$match === 0) {
                throw new Exception('The segment condition \'' . $operand . '\' is not valid.');
            }

            $leftMember = $matches[1];
            $operation = $matches[2];
            $valueRightMember = urldecode($matches[3]);

            // is null / is not null
            if ($valueRightMember === '') {
                if ($operation === self::MATCH_NOT_EQUAL) {
                    $operation = self::MATCH_IS_NOT_NULL_NOR_EMPTY;
                } elseif ($operation === self::MATCH_EQUAL) {
                    $operation = self::MATCH_IS_NULL_OR_EMPTY;
                } else {
                    throw new Exception('The segment \'' . $operand . '\' has no value specified. You can leave this value empty ' . 'only when you use the operators: ' . self::MATCH_NOT_EQUAL . ' (is not) or ' . self::MATCH_EQUAL . ' (is)', 1628398783);
                }
            }

            $parsedSubExpressions[] = [
                self::INDEX_BOOL_OPERATOR => $operator,
                self::INDEX_OPERAND => [
                    self::INDEX_OPERAND_NAME => $leftMember,
                    self::INDEX_OPERAND_OPERATOR => $operation,
                    self::INDEX_OPERAND_VALUE => $valueRightMember,
                ]];
        }
        $this->parsedSubExpressions = $parsedSubExpressions;
        return $parsedSubExpressions;
    }

    /**
     * @param array $availableTables
     * @throws Exception
     */
    public function parseSubExpressionsIntoSqlExpressions(array &$availableTables = []): void
    {
        $sqlSubExpressions = [];
        $this->valuesBind = [];
        $this->joins = [];

        foreach ($this->parsedSubExpressions as $leaf) {
            $operator = $leaf[self::INDEX_BOOL_OPERATOR];
            $operandDefinition = $leaf[self::INDEX_OPERAND];

            $operand = $this->getSqlMatchFromDefinition($operandDefinition, $availableTables);

            if ($operand === null) {
                continue;
            }

            if ($operand[self::INDEX_OPERAND_OPERATOR] !== null) {
                if (is_array($operand[self::INDEX_OPERAND_OPERATOR])) {
                    $this->valuesBind = array_merge($this->valuesBind, $operand[self::INDEX_OPERAND_OPERATOR]);
                } else {
                    $this->valuesBind[] = $operand[self::INDEX_OPERAND_OPERATOR];
                }
            }

            $operand = $operand[self::INDEX_OPERAND_NAME];

            $sqlSubExpressions[] = [
                self::INDEX_BOOL_OPERATOR => $operator,
                self::INDEX_OPERAND => $operand,
            ];
        }

        $this->tree = $sqlSubExpressions;
    }

    /**
     * Given an array representing one filter operand ( left member , operation , right member)
     * Will return an array containing
     * - the SQL substring,
     * - the values to bind to this substring
     *
     * @param array $def
     * @param array $availableTables
     * @return array
     * @throws Exception
     */
    protected function getSqlMatchFromDefinition(array $def, array &$availableTables): ?array
    {
        $field = $def[0];
        $matchType = $def[1];
        $value = $def[2];

        if (empty($value)) {
            return null;
        }

        // Segment::getCleanedExpression() may return array(null, $matchType, null)
        $operandWillNotMatchAnyRow = empty($field) && $value === null;
        if ($operandWillNotMatchAnyRow) {
            if ($matchType === self::MATCH_EQUAL) {
                // eg. pageUrl==DoesNotExist
                // Equal to NULL means it will match none
                $phpExpression = self::SQL_WHERE_DO_NOT_MATCH_ANY_ROW;
            } elseif ($matchType === self::MATCH_NOT_EQUAL) {
                // eg. pageUrl!=DoesNotExist
                // Not equal to NULL means it matches all rows
                $phpExpression = self::SQL_WHERE_MATCHES_ALL_ROWS;
            } elseif ($matchType === self::MATCH_CONTAINS
                || $matchType === self::MATCH_DOES_NOT_CONTAIN
                || $matchType === self::MATCH_STARTS_WITH
                || $matchType === self::MATCH_ENDS_WITH) {
                // no action was found for CONTAINS / DOES NOT CONTAIN
                // eg. pageUrl=@DoesNotExist -> matches no row
                // eg. pageUrl!@DoesNotExist -> matches no rows
                $phpExpression = self::SQL_WHERE_DO_NOT_MATCH_ANY_ROW;
            } else {
                // it is not expected to reach this code path
                throw new Exception('Unexpected match type $matchType for your segment. Please report this issue to the Matomo team with the segment you are using.');
            }

            return [$phpExpression, $value = null];
        }

        $alsoMatchNULLValues = false;
        switch ($matchType) {
            case self::MATCH_EQUAL:
                $condition = "{field} === '{value}'";
                break;
            case self::MATCH_NOT_EQUAL:
                $condition = "{field} !== '{value}'";
                break;
            case self::MATCH_GREATER:
                $condition = "{field} > '{value}'";
                break;
            case self::MATCH_LESS:
                $condition = "{field} < '{value}'";
                break;
            case self::MATCH_GREATER_OR_EQUAL:
                $condition = "{field} >= '{value}'";
                break;
            case self::MATCH_LESS_OR_EQUAL:
                $condition = "{field} <= '{value}'";
                break;
            case self::MATCH_CONTAINS:
                $condition = 'stripos((string){field}, \'{value}\') !== false';
                break;
            case self::MATCH_DOES_NOT_CONTAIN:
                $condition = 'stripos((string){field}, \'{value}\') === false';
                break;
            case self::MATCH_STARTS_WITH:
                $condition = "str_starts_with(strtolower((string){field}), strtolower('{value}'))";
                break;
            case self::MATCH_ENDS_WITH:
                $condition = "str_ends_with(strtolower((string){field}), strtolower('{value}'))";
                break;
            case self::MATCH_IS_NOT_NULL_NOR_EMPTY:
                $condition = '%s IS NOT NULL AND %s <> \'\' AND %s <> \'0\'';
                $value = null;
                throw new \Exception('Not implemented yet');
                break;

            case self::MATCH_IS_NULL_OR_EMPTY:
                $condition = "empty({field})";
                break;

            case self::MATCH_ACTIONS_CONTAINS:
                // this match type is not accessible from the outside
                // (it won't be matched in self::parseSubExpressions())
                // it can be used internally to inject sub-expressions into the query.
                // see Segment::getCleanedExpression()
                $condition = '%s IN (' . $value['SQL'] . ')';
                $value = $value['bind'];
                throw new \Exception('Not implemented yet');
                break;
            case self::MATCH_ACTIONS_NOT_CONTAINS:
                // this match type is not accessible from the outside
                // (it won't be matched in self::parseSubExpressions())
                // it can be used internally to inject sub-expressions into the query.
                // see Segment::getCleanedExpression()
                $condition = '%s NOT IN (' . $value['sql'] . ')';
                $value = $value['bind'];
                throw new \Exception('Not implemented yet');
                break;
            default:
                throw new Exception("Filter contains the match type '" . $matchType . "' which is not supported");
                break;
        }

        // We match NULL values when rows are excluded only when we are not doing a
        $alsoMatchNULLValues = $alsoMatchNULLValues && !empty($value);
        $condition = str_replace('{key}', $field, $condition);

        if ($matchType === self::MATCH_ACTIONS_CONTAINS || $matchType === self::MATCH_ACTIONS_NOT_CONTAINS || $value === null) {
            $phpExpression = "( $condition )";
        } else {
            if ($alsoMatchNULLValues) {
                $phpExpression = "( $field IS NULL OR $condition {value} )";
            } else {
                $phpExpression = $condition;
            }
        }

        if (isset($this->matomoToAnalyticsFieldMapping[$field])) {
            $phpExpression = str_replace(['{field}', '{value}'], ['($data[\'' . $this->matomoToAnalyticsFieldMapping[$field] . '\'] ?? \'\') ', $value], $phpExpression);
        } else {
            $this->logger->warning(sprintf('MatomoToAnalyticsFieldMapping is missing for field %s', $field), LogEnvironment::fromMethodName(__METHOD__));
            return null;
        }

        return [$phpExpression, $value];
    }

    /**
     * Escape the characters % and _ in the given string
     * @param string $str
     * @return string
     */
    private function escapeLikeString($str)
    {
        if (false !== strpos($str, '%')) {
            $str = str_replace("%", "\%", $str);
        }

        if (false !== strpos($str, '_')) {
            $str = str_replace("_", "\_", $str);
        }

        return $str;
    }

    /**
     * Given a filter string,
     * will parse it into an array where each row contains the boolean operator applied to it,
     * and the operand
     *
     * @return array
     */
    protected function parseTree()
    {
        $string = $this->segmentDefinitionString;

        if (empty($string)) {
            return [];
        }

        $tree = [];
        $i = 0;
        $length = strlen($string);
        $isBackslash = false;
        $operand = '';

        while ($i <= $length) {
            $char = $string[$i];

            $isAND = ($char == self::AND_DELIMITER);
            $isOR = ($char == self::OR_DELIMITER);
            $isEnd = ($length == $i + 1);

            if ($isEnd) {
                if ($isBackslash && ($isAND || $isOR)) {
                    $operand = substr($operand, 0, -1);
                }
                $operand .= $char;
                $tree[] = [self::INDEX_BOOL_OPERATOR => self::BOOL_OPERATOR_END, self::INDEX_OPERAND => $operand];
                break;
            }

            if ($isAND && !$isBackslash) {
                $tree[] = [self::INDEX_BOOL_OPERATOR => self::BOOL_OPERATOR_AND, self::INDEX_OPERAND => $operand];
                $operand = '';
            } elseif ($isOR && !$isBackslash) {
                $tree[] = [self::INDEX_BOOL_OPERATOR => self::BOOL_OPERATOR_OR, self::INDEX_OPERAND => $operand];
                $operand = '';
            } else {
                if ($isBackslash && ($isAND || $isOR)) {
                    $operand = substr($operand, 0, -1);
                }
                $operand .= $char;
            }
            $isBackslash = ($char == "\\");
            $i++;
        }
        return $tree;
    }

    /**
     * Given the array of parsed boolean logic, will return
     * an array containing the full SQL string representing the filter,
     * the needed joins and the values to bind to the query
     *
     * @return string SQL Query, Joins and Bind parameters
     * @throws Exception
     */
    public function getExpression(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $sql = '';
        $subExpression = false;

        foreach ($this->tree as $key => $expression) {
            $operator = $expression[self::INDEX_BOOL_OPERATOR];
            $operand = $expression[self::INDEX_OPERAND];

            if ($operator === self::BOOL_OPERATOR_OR
                && !$subExpression
            ) {
                $sql .= ' (';
                $subExpression = true;
            } else {
                $sql .= '';
            }

            $sql .= $operand;

            if ($operator === self::BOOL_OPERATOR_AND
                && $subExpression
            ) {
                $sql .= ')';
                $subExpression = false;
            }

            if (count($this->tree) > $key + 1) {
                $sql .= $operator;
            }
        }

        if ($subExpression) {
            $sql .= ')';
        }

        return sprintf($sql, ...$this->valuesBind);
    }
}
