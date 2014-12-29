<?php
/**
 *
 * This file is part of the Aura project for PHP.
 *
 * @package Aura.Filter
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Filter\Rule\Validate;

use PDO;
use PDOStatement;

/**
 *
 * Rule for an SQL table column value.
 *
 * @package Aura.Filter
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
class InTableColumn
{
    /**
     *
     * A PDO database connection.
     *
     * @var PDO
     *
     */
    protected $pdo;

    /**
     *
     * The prefix to use when quoting identifier names.
     *
     * @var string
     *
     */
    protected $quote_name_prefix = '';

    /**
     *
     * The suffix to use when quoting identifier names.
     *
     * @var string
     *
     */
    protected $quote_name_suffix = '';

    /**
     *
     * Constructor.
     *
     * @param PDO $pdo A PDO database connection.
     *
     */
    public function __construct(
        PDO $pdo,
        $quote_name_prefix = '',
        $quote_name_suffix = ''
    ) {
        $this->pdo = $pdo;
        $this->quote_name_prefix = $quote_name_prefix;
        $this->quote_name_suffix = $quote_name_suffix;
    }

    /**
     *
     * Validates that the value exists in a table column; the value will be
     * securely bound into a prepared statement.
     *
     * @param string $table The table to select from. This name will not be
     * escaped or otherwise secured. Never pass user input as a name here.
     *
     * @param string $column The column to select for the value. This name will
     * not be escaped or otherwise secured. Never pass user input as a value
     * here.
     *
     * @param string $where Additional WHERE conditions. This string is appended
     * to the query using `AND ($where)` and is not escaped or sanitized.
     *
     * @return bool True if valid, false if not.
     *
     */
    public function __invoke($object, $field, $table, $column, $where = null)
    {
        $stm = $this->buildSelect($table, $column, $where);
        $sth = $this->pdo->prepare($stm);
        $sth->execute($this->getBindValues($object, $field, $column, $where));
        return $sth->fetchColumn() !== false;
    }

    /**
     *
     * Returns the SELECT statement to find the column value.
     *
     * @param string $table The table to select from. This name will not be
     * escaped or otherwise secured. Never pass user input as a value here.
     *
     * @param string $column The column to select for the value. This name will
     * not be escaped or otherwise secured. Never pass user input as a value
     * here.
     *
     * @param string $where Additional WHERE conditions. This string is appended
     * to the query using `AND ($where)` and is not escaped or sanitized.
     *
     * @return string
     *
     */
    protected function buildSelect($table, $column, $where = null)
    {
        $quoted_table = $this->quoteName($table);
        $quoted_column = $this->quoteName($column);
        $select = "SELECT {$quoted_column} "
                . "FROM {$quoted_table} "
                . "WHERE {$quoted_column} = :{$column}";
        if ($where) {
            $select .= " AND ({$where})";
        }
        return $select;
    }

    /**
     *
     * A brain-dead automatic binding mechanism. Anything that looks like a
     * named placeholder in the $where string is assumed to be a field name
     * on the object being filtered, and its value is bound into the statement.
     *
     * @param string $column The column being selected for its value.
     *
     * @param string $where Additional WHERE conditions.
     *
     * @return null
     *
     */
    protected function getBindValues($object, $field, $column, $where)
    {
        $bind = array($column => $object->$field);
        if (! $where) {
            return $bind;
        }

        preg_match_all('/:[_a-zA-Z][_a-zA-Z0-9]*/', $where, $matches);
        if (empty($matches[0])) {
            return $bind;
        }

        $placeholders = $matches[0];
        foreach ($placeholders as $placeholder) {
            // strip the leading ":"
            $field = substr($placeholder, 1);
            if (isset($object->$field)) {
                $bind[$field] = $object->$field;
            }
        }
        return $bind;
    }

    /**
     *
     * Puts quotes around identifier names (table, column, etc.).
     *
     * @param string $name The identifier name to quote. This name will not be
     * escaped or otherwise secured. Never pass user input as a value here.
     *
     * @return string The identifier name with quotes around it.
     *
     */
    protected function quoteName($name)
    {
        return $this->quote_name_prefix . $name . $this->quote_name_suffix;
    }
}