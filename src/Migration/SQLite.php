<?php

namespace atk4\schema\Migration;

class SQLite extends \atk4\schema\Migration
{
    /** @var string Expression to create primary key */
    public $primary_key_expr = 'primary key autoincrement';

    public $mapToAgile = [
        0 => ['string'],
    ];

    /**
     * Return database table descriptions.
     * DB engine specific.
     */
    public function describeTable(string $table): array
    {
        return $this->connection->expr('pragma table_info({})', [$table])->get();
    }

    /**
     * Convert Agile Data field types to SQL field types.
     *
     * @param string $type    Agile Data field type
     * @param array  $options More options
     */
    public function getSQLFieldType(?string $type, array $options = []): ?string
    {
        $res = parent::getSQLFieldType($type, $options);

        // fix PK datatype to "integer primary key"
        // see https://www.sqlite.org/lang_createtable.html#rowid
        // all other datatypes (like "bigint", "integer unsinged", "integer not null") are not supported
        if (!empty($options['ref_type']) && $options['ref_type'] === self::REF_TYPE_PRIMARY) {
            $res = preg_replace('~(?:big)?int(?:eger)?\s+(unsigned\s+)?(not null\s+)?~', 'integer ', $res);
        }

        return $res;
    }
}
