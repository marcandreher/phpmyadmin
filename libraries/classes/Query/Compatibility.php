<?php

declare(strict_types=1);

namespace PhpMyAdmin\Query;

use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Util;

use function is_string;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;

/**
 * Handles data compatibility from SQL query results
 */
class Compatibility
{
    public static function getISCompatForGetTablesFull(array $eachTables, string $eachDatabase): array
    {
        foreach ($eachTables as $table_name => $_) {
            if (
                ! isset($eachTables[$table_name]['Type'])
                && isset($eachTables[$table_name]['Engine'])
            ) {
                // pma BC, same parts of PMA still uses 'Type'
                $eachTables[$table_name]['Type']
                    =& $eachTables[$table_name]['Engine'];
            } elseif (
                ! isset($eachTables[$table_name]['Engine'])
                && isset($eachTables[$table_name]['Type'])
            ) {
                // old MySQL reports Type, newer MySQL reports Engine
                $eachTables[$table_name]['Engine']
                    =& $eachTables[$table_name]['Type'];
            }

            // Compatibility with INFORMATION_SCHEMA output
            $eachTables[$table_name]['TABLE_SCHEMA']
                = $eachDatabase;
            $eachTables[$table_name]['TABLE_NAME']
                =& $eachTables[$table_name]['Name'];
            $eachTables[$table_name]['ENGINE']
                =& $eachTables[$table_name]['Engine'];
            $eachTables[$table_name]['VERSION']
                =& $eachTables[$table_name]['Version'];
            $eachTables[$table_name]['ROW_FORMAT']
                =& $eachTables[$table_name]['Row_format'];
            $eachTables[$table_name]['TABLE_ROWS']
                =& $eachTables[$table_name]['Rows'];
            $eachTables[$table_name]['AVG_ROW_LENGTH']
                =& $eachTables[$table_name]['Avg_row_length'];
            $eachTables[$table_name]['DATA_LENGTH']
                =& $eachTables[$table_name]['Data_length'];
            $eachTables[$table_name]['MAX_DATA_LENGTH']
                =& $eachTables[$table_name]['Max_data_length'];
            $eachTables[$table_name]['INDEX_LENGTH']
                =& $eachTables[$table_name]['Index_length'];
            $eachTables[$table_name]['DATA_FREE']
                =& $eachTables[$table_name]['Data_free'];
            $eachTables[$table_name]['AUTO_INCREMENT']
                =& $eachTables[$table_name]['Auto_increment'];
            $eachTables[$table_name]['CREATE_TIME']
                =& $eachTables[$table_name]['Create_time'];
            $eachTables[$table_name]['UPDATE_TIME']
                =& $eachTables[$table_name]['Update_time'];
            $eachTables[$table_name]['CHECK_TIME']
                =& $eachTables[$table_name]['Check_time'];
            $eachTables[$table_name]['TABLE_COLLATION']
                =& $eachTables[$table_name]['Collation'];
            $eachTables[$table_name]['CHECKSUM']
                =& $eachTables[$table_name]['Checksum'];
            $eachTables[$table_name]['CREATE_OPTIONS']
                =& $eachTables[$table_name]['Create_options'];
            $eachTables[$table_name]['TABLE_COMMENT']
                =& $eachTables[$table_name]['Comment'];

            if (
                strtoupper($eachTables[$table_name]['Comment'] ?? '') === 'VIEW'
                && $eachTables[$table_name]['Engine'] == null
            ) {
                $eachTables[$table_name]['TABLE_TYPE'] = 'VIEW';
            } elseif ($eachDatabase === 'information_schema') {
                $eachTables[$table_name]['TABLE_TYPE'] = 'SYSTEM VIEW';
            } else {
                /**
                 * @todo difference between 'TEMPORARY' and 'BASE TABLE'
                 * but how to detect?
                 */
                $eachTables[$table_name]['TABLE_TYPE'] = 'BASE TABLE';
            }
        }

        return $eachTables;
    }

    public static function getISCompatForGetColumnsFull(array $columns, string $database, string $table): array
    {
        $ordinal_position = 1;
        foreach ($columns as $column_name => $_) {
            // Compatibility with INFORMATION_SCHEMA output
            $columns[$column_name]['COLUMN_NAME']
                =& $columns[$column_name]['Field'];
            $columns[$column_name]['COLUMN_TYPE']
                =& $columns[$column_name]['Type'];
            $columns[$column_name]['COLLATION_NAME']
                =& $columns[$column_name]['Collation'];
            $columns[$column_name]['IS_NULLABLE']
                =& $columns[$column_name]['Null'];
            $columns[$column_name]['COLUMN_KEY']
                =& $columns[$column_name]['Key'];
            $columns[$column_name]['COLUMN_DEFAULT']
                =& $columns[$column_name]['Default'];
            $columns[$column_name]['EXTRA']
                =& $columns[$column_name]['Extra'];
            $columns[$column_name]['PRIVILEGES']
                =& $columns[$column_name]['Privileges'];
            $columns[$column_name]['COLUMN_COMMENT']
                =& $columns[$column_name]['Comment'];

            $columns[$column_name]['TABLE_CATALOG'] = null;
            $columns[$column_name]['TABLE_SCHEMA'] = $database;
            $columns[$column_name]['TABLE_NAME'] = $table;
            $columns[$column_name]['ORDINAL_POSITION'] = $ordinal_position;
            $colType = $columns[$column_name]['COLUMN_TYPE'];
            $colType = is_string($colType) ? $colType : '';
            $colTypePosComa = strpos($colType, '(');
            $colTypePosComa = $colTypePosComa !== false ? $colTypePosComa : strlen($colType);
            $columns[$column_name]['DATA_TYPE']
                = substr(
                    $colType,
                    0,
                    $colTypePosComa
                );
            /**
             * @todo guess CHARACTER_MAXIMUM_LENGTH from COLUMN_TYPE
            */
            $columns[$column_name]['CHARACTER_MAXIMUM_LENGTH'] = null;
            /**
             * @todo guess CHARACTER_OCTET_LENGTH from CHARACTER_MAXIMUM_LENGTH
             */
            $columns[$column_name]['CHARACTER_OCTET_LENGTH'] = null;
            $columns[$column_name]['NUMERIC_PRECISION'] = null;
            $columns[$column_name]['NUMERIC_SCALE'] = null;
            $colCollation = $columns[$column_name]['COLLATION_NAME'];
            $colCollation = is_string($colCollation) ? $colCollation : '';
            $colCollationPosUnderscore = strpos($colCollation, '_');
            $colCollationPosUnderscore = $colCollationPosUnderscore !== false
                ? $colCollationPosUnderscore
                : strlen($colCollation);
            $columns[$column_name]['CHARACTER_SET_NAME']
                = substr(
                    $colCollation,
                    0,
                    $colCollationPosUnderscore
                );

            $ordinal_position++;
        }

        return $columns;
    }

    public static function isMySqlOrPerconaDb(): bool
    {
        $serverType = Util::getServerType();

        return $serverType === 'MySQL' || $serverType === 'Percona Server';
    }

    public static function isMariaDb(): bool
    {
        $serverType = Util::getServerType();

        return $serverType === 'MariaDB';
    }

    public static function isMySql(): bool
    {
        $serverType = Util::getServerType();

        return $serverType === 'MySQL';
    }

    public static function isCompatibleRenameIndex(int $serverVersion): bool
    {
        if (self::isMySqlOrPerconaDb()) {
            return $serverVersion >= 50700;
        }

        // @see https://mariadb.com/kb/en/alter-table/#rename-indexkey
        if (self::isMariaDb()) {
            return $serverVersion >= 100502;
        }

        return false;
    }

    public static function supportsReferencesPrivilege(DatabaseInterface $dbi): bool
    {
        // See: https://mariadb.com/kb/en/grant/#table-privileges
        // Unused
        if ($dbi->isMariaDB()) {
            return false;
        }

        // https://dev.mysql.com/doc/refman/5.6/en/privileges-provided.html#priv_references
        // This privilege is unused before MySQL 5.6.22.
        // As of 5.6.22, creation of a foreign key constraint
        // requires at least one of the SELECT, INSERT, UPDATE, DELETE,
        // or REFERENCES privileges for the parent table.
        return $dbi->getVersion() >= 50622;
    }

    /**
     * Returns whether the database server supports virtual columns
     */
    public static function isVirtualColumnsSupported(int $serverVersion): bool
    {
        if (self::isMySqlOrPerconaDb()) {
            return $serverVersion >= 50705;
        }

        // @see https://daniel-bartholomew.com/2010/09/30/road-to-mariadb-5-2-virtual-columns/
        if (self::isMariaDb()) {
            return $serverVersion >= 50200;
        }

        return false;
    }

    /**
     * Returns whether the database server supports virtual columns
     */
    public static function supportsStoredKeywordForVirtualColumns(int $serverVersion): bool
    {
        // @see https://mariadb.com/kb/en/generated-columns/#mysql-compatibility-support
        if (self::isMariaDb()) {
            return $serverVersion >= 100201;
        }

        return false;
    }
}
