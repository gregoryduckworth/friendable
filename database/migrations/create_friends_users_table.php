<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom SQLite Grammar that preserves varchar/char lengths for Laravel 11+ compatibility
 */
if (!class_exists('SQLiteGrammarWithLengths')) {
    class SQLiteGrammarWithLengths extends \Illuminate\Database\Schema\Grammars\SQLiteGrammar
    {
        /**
         * Create the column definition for a char type.
         *
         * @param  \Illuminate\Support\Fluent  $column
         * @return string
         */
        protected function typeChar(\Illuminate\Support\Fluent $column)
        {
            if (isset($column->length)) {
                return "varchar({$column->length})";
            }
            return 'varchar';
        }

        /**
         * Create the column definition for a string type.
         *
         * @param  \Illuminate\Support\Fluent  $column
         * @return string
         */
        protected function typeString(\Illuminate\Support\Fluent $column)
        {
            if (isset($column->length)) {
                return "varchar({$column->length})";
            }
            return 'varchar';
        }
        
        /**
         * Create the column definition for a uuid type.
         *
         * @param  \Illuminate\Support\Fluent  $column
         * @return string
         */
        protected function typeUuid(\Illuminate\Support\Fluent $column)
        {
            return 'varchar(36)';
        }
    }
}

return new class extends Migration
{
    /**
     * Track column lengths for SQLite varchar columns (Laravel 11+  compatibility)
     * @var array
     */
    protected $columnLengths = [];
    
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = Schema::getConnection();
        
        // For SQLite in Laravel 11+, temporarily use a custom grammar that preserves varchar lengths
        $originalGrammar = null;
        if ($connection->getDriverName() === 'sqlite') {
            // Swap in our custom grammar
            $originalGrammar = $connection->getSchemaGrammar();
            $connection->setSchemaGrammar(new SQLiteGrammarWithLengths());
        }
        
        try {
            // Get a fresh schema builder that will use the new grammar
            $schemaBuilder = $connection->getSchemaBuilder();
            
            $schemaBuilder->create('friends_users', function (Blueprint $table) {
                // Create foreign key columns that match the users.id definition exactly
                $this->createMatchingColumn($table, 'friend_id');
                $this->createMatchingColumn($table, 'user_id');
                
                $table->string('status');

                $table->foreign('user_id')->references('id')->on('users');
                $table->foreign('friend_id')->references('id')->on('users');

                $table->primary(array('user_id', 'friend_id'));
            });
        } finally {
            // Restore original grammar
            if ($originalGrammar) {
                $connection->setSchemaGrammar($originalGrammar);
            }
        }
    }
    
    /**
     * Check if we should use raw DDL for SQLite (Laravel 11+ compatibility)
     *
     * @return bool
     */
    protected function shouldUseSQLiteDDL()
    {
        $connection = Schema::getConnection();
        
        // Check if the users table exists and has varchar columns without lengths
        try {
            $result = $connection->selectOne(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name='users'"
            );
            
            if ($result && strpos($result->sql, 'varchar') !== false) {
                // Check if varchar columns have explicit lengths
                // If they don't, we're likely in Laravel 11+
                return !preg_match('/varchar\s*\(\d+\)/i', $result->sql);
            }
        } catch (\Exception $e) {
            // If we can't check, default to Blueprint approach
        }
        
        return false;
    }
    
    /**
     * Create the friends_users table using raw DDL for SQLite Laravel 11+.
     * This preserves varchar column lengths by copying them from the users table.
     *
     * @return void
     */
    protected function createTableUsingSQLiteDDL()
    {
        $connection = Schema::getConnection();
        
        // Get the users table definition
        $usersTable = $connection->selectOne(
            "SELECT sql FROM sqlite_master WHERE type='table' AND name='users'"
        );
        
        if (!$usersTable) {
            throw new \RuntimeException('users table must exist before running this migration');
        }
        
        // Extract the users.id column definition
        $usersSql = $usersTable->sql;
        $idColumnDef = null;
        $idLength = null;
        
        // Try to extract any explicit length from the users.id column
        if (preg_match('/"id"\s+(?:var)?char\s*\((\d+)\)/i', $usersSql, $matches)) {
            $idLength = (int)$matches[1];
            $idColumnDef = "varchar({$idLength})";
        } elseif (preg_match('/"id"\s+varchar/i', $usersSql)) {
            // varchar without explicit length - try to infer it
            // Check if there's data we can sample
            try {
                $count = $connection->selectOne("SELECT COUNT(*) as count FROM users");
                if ($count && $count->count > 0) {
                    $sample = $connection->selectOne("SELECT LENGTH(id) as len FROM users LIMIT 1");
                    if ($sample && $sample->len) {
                        $idLength = (int)$sample->len;
                        $idColumnDef = "varchar({$idLength})";
                    }
                }
            } catch (\Exception $e) {
                // No data or can't sample
            }
            
            // If we still don't have a length, use varchar without length
            // (will be handled by foreign key type matching)
            if (!$idColumnDef) {
                $idColumnDef = 'varchar';
            }
        } else {
            // Not a varchar/char column - extract the type as-is
            if (preg_match('/"id"\s+([a-z]+)/i', $usersSql, $matches)) {
                $idColumnDef = strtolower($matches[1]);
            } else {
                $idColumnDef = 'varchar';
            }
        }
        
        // Create the friends_users table with the copied column definition
        $ddl = <<<SQL
CREATE TABLE "friends_users" (
    "friend_id" {$idColumnDef} not null,
    "user_id" {$idColumnDef} not null,
    "status" varchar not null,
    foreign key("user_id") references "users"("id"),
    foreign key("friend_id") references "users"("id"),
    primary key ("user_id", "friend_id")
)
SQL;
        
        $connection->statement($ddl);
    }
    
    /**
     * Fix SQLite column lengths by recreating the table with explicit lengths.
     * This is necessary because Laravel 11+ SQLite grammar doesn't preserve varchar lengths.
     *
     * @return void
     */
    protected function fixSQLiteColumnLengths()
    {
        $connection = Schema::getConnection();
        
        if ($connection->getDriverName() !== 'sqlite' || empty($this->columnLengths)) {
            return; // Only needed for SQLite when we have tracked lengths
        }
        
        // Get the current table SQL
        $result = $connection->selectOne(
            "SELECT sql FROM sqlite_master WHERE type='table' AND name='friends_users'"
        );
        
        if (!$result) {
            return;
        }
        
        $originalSql = $result->sql;
        $modifiedSql = $originalSql;
        $hasChanges = false;
        
        // Replace each column definition to include the tracked length
        foreach ($this->columnLengths as $columnName => $info) {
            $length = $info['length'];
            $type = $info['type'];
            
            // Match the column definition without length: "column_name" varchar
            $pattern = '/(["' . "'" . ']?)(' . preg_quote($columnName, '/') . ')\1\s+varchar(?!\()/i';
            $replacement = "$1$2$1 {$type}({$length})";
            
            if (preg_match($pattern, $modifiedSql)) {
                $modifiedSql = preg_replace($pattern, $replacement, $modifiedSql);
                $hasChanges = true;
            }
        }
        
        if (!$hasChanges) {
            return;
        }
        
        // Recreate the table with the modified SQL
        $connection->transaction(function() use ($connection, $modifiedSql) {
            // Rename current table to temporary name
            $connection->statement('ALTER TABLE friends_users RENAME TO friends_users_temp');
            
            // Create new table with correct column lengths
            $connection->statement($modifiedSql);
            
            // Copy data from temp table to new table
            $connection->statement('INSERT INTO friends_users SELECT * FROM friends_users_temp');
            
            // Drop temporary table
            $connection->statement('DROP TABLE friends_users_temp');
        });
    }
    
    /**
     * Normalize database-specific type names to generic Laravel types.
     *
     * @param string $type
     * @return string
     */
    protected function normalizeTypeName($type)
    {
        $type = strtolower($type);
        
        // PostgreSQL native type mappings
        $map = [
            'int8' => 'bigint',
            'int4' => 'integer',
            'int2' => 'smallint',
            'bpchar' => 'char',
            // MySQL synonyms
            'int' => 'integer',
            'tinyint' => 'smallint',
            // Common aliases
            'biginteger' => 'bigint',
            'smallinteger' => 'smallint',
            'guid' => 'uuid',
        ];
        
        return $map[$type] ?? $type;
    }
    
    /**
     * Add a string column with explicit length, tracking it for SQLite length preservation.
     *
     * @param \Illuminate\Database\Schema\Blueprint $table
     * @param string $columnName
     * @param int $length
     * @param string $type
     * @return void
     */
    protected function addStringColumnWithLength($table, $columnName, $length, $type = 'varchar')
    {
        $connection = Schema::getConnection();
        $driverName = $connection->getDriverName();
        
        // Track the length for SQLite (Laravel 11+ doesn't preserve it)
        if ($driverName === 'sqlite') {
            $this->columnLengths[$columnName] = [
                'length' => $length,
                'type' => $type
            ];
        }
        
        // Use standard Blueprint methods (for SQLite this creates varchar without length,
        // but we'll fix it later using the tracked lengths)
        if ($type === 'char') {
            $table->char($columnName, $length);
        } else {
            $table->string($columnName, $length);
        }
    }
    
    /**
     * Get the actual length of a column from the database.
     *
     * @param \Illuminate\Database\Connection $connection
     * @param string $tableName
     * @param string $columnName
     * @return int|null
     */
    protected function getColumnLength($connection, $tableName, $columnName)
    {
        $driverName = $connection->getDriverName();
        
        try {
            if ($driverName === 'mysql') {
                $result = $connection->selectOne(
                    "SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$connection->getDatabaseName(), $tableName, $columnName]
                );
                return $result->CHARACTER_MAXIMUM_LENGTH ?? null;
            } elseif ($driverName === 'pgsql') {
                $result = $connection->selectOne(
                    "SELECT character_maximum_length FROM information_schema.columns 
                     WHERE table_name = ? AND column_name = ?",
                    [$tableName, $columnName]
                );
                return $result->character_maximum_length ?? null;
            } elseif ($driverName === 'sqlite') {
                $result = $connection->selectOne(
                    "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
                    [$tableName]
                );
                // Parse CREATE TABLE statement to extract length
                // Example: id char(26) or id varchar(255) or "id" varchar(36)
                // Handle both quoted and unquoted column names
                $quotedColumn = preg_quote($columnName, '/');
                if ($result && preg_match("/[\"']?{$quotedColumn}[\"']?\s+(?:var)?char\((\d+)\)/i", $result->sql, $matches)) {
                    return (int)$matches[1];
                }
            } elseif ($driverName === 'sqlsrv') {
                $result = $connection->selectOne(
                    "SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$tableName, $columnName]
                );
                return $result->CHARACTER_MAXIMUM_LENGTH ?? null;
            }
        } catch (\Exception $e) {
            // Return null if unable to determine length
        }
        
        return null;
    }
    
    /**
     * Infer the intended length of a string column when it's not explicitly stored.
     * This is necessary for Laravel 11+ SQLite where varchar lengths aren't preserved.
     *
     * @param \Illuminate\Database\Connection $connection
     * @param string $tableName
     * @param string $columnName
     * @return int|null
     */
    protected function inferStringColumnLength($connection, $tableName, $columnName)
    {
        $driverName = $connection->getDriverName();
        
        if ($driverName !== 'sqlite') {
            return null; // Only needed for SQLite
        }
        
        try {
            // Check if the column is a primary key
            $result = $connection->selectOne(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
                [$tableName]
            );
            
            if ($result) {
                $sql = $result->sql;
                $quotedColumn = preg_quote($columnName, '/');
                
                // Check if it's defined as primary key in the column definition
                // Patterns: "id" varchar ... primary key OR primary key ("id")
                $isPrimaryKey = preg_match("/[\"']?{$quotedColumn}[\"']?\s+varchar\s+[^,]*primary\s+key/i", $sql) ||
                                preg_match("/primary\s+key\s*\([\"']?{$quotedColumn}[\"']?\)/i", $sql);
                
                if ($isPrimaryKey) {
                    // Primary key varchar columns are typically UUIDs (36 chars) or ULIDs (26 chars)
                    // Try to detect by checking if there's actual data
                    $count = $connection->selectOne("SELECT COUNT(*) as count FROM {$tableName}");
                    
                    if ($count && $count->count > 0) {
                        // Sample the first row to infer length
                        $sample = $connection->selectOne("SELECT LENGTH({$columnName}) as len FROM {$tableName} LIMIT 1");
                        if ($sample && $sample->len) {
                            return (int)$sample->len;
                        }
                    }
                    
                    // No data yet - try to infer from other columns or table structure
                    // Check if table has created_at/updated_at (common with UUID/ULID usage)
                    // This isn't perfect but helps distinguish between common patterns
                    
                    // Try a different approach: Examine all columns to see if we can detect the pattern
                    // If there are timestamp columns, it's likely a modern Laravel table using UUIDs
                    $hasTimestamps = preg_match('/(created_at|updated_at)\s+datetime/i', $sql);
                    
                    if ($hasTimestamps) {
                        // Modern Laravel table - likely UUID (36) or ULID (26)
                        // We can't distinguish without data, so default to UUID (more common)
                        return 36;
                    }
                    
                    // Fallback to UUID length (36) as it's most common for primary key varchars
                    return 36;
                }
            }
        } catch (\Exception $e) {
            // If we can't determine, return null to use the fallback default
        }
        
        return null;
    }
    
    /**
     * Determine if an integer column is unsigned by checking the database.
     *
     * @param \Illuminate\Database\Connection $connection
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    protected function isColumnUnsigned($connection, $tableName, $columnName)
    {
        $driverName = $connection->getDriverName();
        
        try {
            if ($driverName === 'mysql') {
                $result = $connection->selectOne(
                    "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$connection->getDatabaseName(), $tableName, $columnName]
                );
                return $result && stripos($result->COLUMN_TYPE, 'unsigned') !== false;
            } elseif ($driverName === 'pgsql') {
                // PostgreSQL doesn't have unsigned integers; all integers are signed
                return false;
            } elseif ($driverName === 'sqlite') {
                // SQLite doesn't enforce signedness; check the CREATE TABLE statement
                $result = $connection->selectOne(
                    "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
                    [$tableName]
                );
                if ($result && preg_match("/{$columnName}\s+[a-z]+\s+unsigned/i", $result->sql)) {
                    return true;
                }
                // Default to unsigned for INTEGER PRIMARY KEY AUTOINCREMENT (SQLite convention)
                return false;
            } elseif ($driverName === 'sqlsrv') {
                // SQL Server doesn't have unsigned integers; all integers are signed
                return false;
            }
        } catch (\Exception $e) {
            // If we can't determine signedness, default to unsigned for safety
            // (unsigned is more common in Laravel migrations)
        }
        
        // Default to unsigned (most common case)
        return true;
    }
    
    /**
     * Create a column that matches the users.id column definition exactly.
     *
     * @param \Illuminate\Database\Schema\Blueprint $table
     * @param string $columnName
     * @return void
     */
    protected function createMatchingColumn($table, $columnName)
    {
        $connection = Schema::getConnection();
        $schemaBuilder = $connection->getSchemaBuilder();
        
        // Attempt to get full column information (Laravel 9.6+)
        try {
            if (method_exists($schemaBuilder, 'getColumns')) {
                $columns = $schemaBuilder->getColumns('users');
                $idColumn = collect($columns)->firstWhere('name', 'id');
                
                if ($idColumn) {
                    $type = $idColumn['type_name'] ?? $idColumn['type'];
                    $length = $idColumn['length'] ?? null;
                    
                    // Normalize database-specific type names (e.g., PostgreSQL int8 -> bigint)
                    $type = $this->normalizeTypeName($type);
                    
                    // Match the exact column definition including length and signedness
                    switch ($type) {
                        case 'bigint':
                            // Check if the column is unsigned
                            $unsigned = $this->isColumnUnsigned($connection, 'users', 'id');
                            if ($unsigned) {
                                $table->unsignedBigInteger($columnName);
                            } else {
                                $table->bigInteger($columnName);
                            }
                            return;
                        case 'integer':
                            // Check if the column is unsigned
                            $unsigned = $this->isColumnUnsigned($connection, 'users', 'id');
                            if ($unsigned) {
                                $table->unsignedInteger($columnName);
                            } else {
                                $table->integer($columnName);
                            }
                            return;
                        case 'smallint':
                            // Check if the column is unsigned
                            $unsigned = $this->isColumnUnsigned($connection, 'users', 'id');
                            if ($unsigned) {
                                $table->unsignedSmallInteger($columnName);
                            } else {
                                $table->smallInteger($columnName);
                            }
                            return;
                        case 'char':
                            // Preserve the exact length for char columns (e.g., ULID uses char(26))
                            // If metadata doesn't include length, query it from the database
                            if ($length === null) {
                                $length = $this->getColumnLength($connection, 'users', 'id');
                            }
                            // Only use default if we can't determine the actual length
                            $length = $length ?? 36;
                            $this->addStringColumnWithLength($table, $columnName, $length, 'char');
                            return;
                        case 'uuid':
                            // For SQLite, use varchar(36) which will be preserved by our custom grammar
                            if ($connection->getDriverName() === 'sqlite') {
                                $table->string($columnName, 36);
                            } else {
                                $table->uuid($columnName);
                            }
                            return;
                        case 'string':
                        case 'varchar':
                            // Preserve the exact length for varchar columns
                            // If metadata doesn't include length, query it from the database
                            if ($length === null) {
                                $length = $this->getColumnLength($connection, 'users', 'id');
                            }
                            
                            // For Laravel 11+ SQLite, varchar columns don't preserve length in metadata
                            // We need to infer the intended length based on context
                            if ($length === null) {
                                // Check if this is likely a UUID/ULID by examining if it's a primary key varchar
                                // Primary key varchar columns are typically UUIDs (36) or ULIDs (26)
                                $length = $this->inferStringColumnLength($connection, 'users', 'id');
                            }
                            
                            // Final fallback to 255 if we still can't determine
                            $length = $length ?? 255;
                            $table->string($columnName, $length);
                            return;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fall through to legacy detection
        }
        
        // Fallback: use getColumnType (Laravel 9.0-9.5) with raw column length query
        try {
            if (method_exists($schemaBuilder, 'getColumnType')) {
                $type = $schemaBuilder->getColumnType('users', 'id');
                
                // Normalize database-specific type names (e.g., PostgreSQL int8 -> bigint)
                $type = $this->normalizeTypeName($type);
                
                // For char/varchar types, retrieve the actual length from the database
                // to ensure foreign key compatibility
                $length = null;
                if (in_array($type, ['char', 'string', 'varchar'])) {
                    $length = $this->getColumnLength($connection, 'users', 'id');
                }
                
                switch ($type) {
                    case 'bigint':
                        // Check if the column is unsigned
                        $unsigned = $this->isColumnUnsigned($connection, 'users', 'id');
                        if ($unsigned) {
                            $table->unsignedBigInteger($columnName);
                        } else {
                            $table->bigInteger($columnName);
                        }
                        return;
                    case 'integer':
                        // Check if the column is unsigned
                        $unsigned = $this->isColumnUnsigned($connection, 'users', 'id');
                        if ($unsigned) {
                            $table->unsignedInteger($columnName);
                        } else {
                            $table->integer($columnName);
                        }
                        return;
                    case 'smallint':
                        // Check if the column is unsigned
                        $unsigned = $this->isColumnUnsigned($connection, 'users', 'id');
                        if ($unsigned) {
                            $table->unsignedSmallInteger($columnName);
                        } else {
                            $table->smallInteger($columnName);
                        }
                        return;
                    case 'char':
                        // Use exact length for char columns (e.g., ULID = 26, UUID = 36)
                        if ($length !== null) {
                            $table->char($columnName, $length);
                        } else {
                            // Safe default for UUID if length cannot be determined
                            // For SQLite, use string(36) which will be preserved by our custom grammar
                            if ($connection->getDriverName() === 'sqlite') {
                                $table->string($columnName, 36);
                            } else {
                                $table->uuid($columnName);
                            }
                        }
                        return;
                    case 'uuid':
                        // For SQLite, use string(36) which will be preserved by our custom grammar
                        if ($connection->getDriverName() === 'sqlite') {
                            $table->string($columnName, 36);
                        } else {
                            $table->uuid($columnName);
                        }
                        return;
                    case 'string':
                    case 'varchar':
                        // Use exact length for varchar columns
                        if ($length !== null) {
                            $table->string($columnName, $length);
                        } else {
                            // For Laravel 11+ SQLite, infer the intended length
                            $inferredLength = $this->inferStringColumnLength($connection, 'users', 'id');
                            $length = $inferredLength ?? 255;
                            $table->string($columnName, $length);
                        }
                        return;
                }
            }
        } catch (\Exception $e) {
            // Fall through to default
        }
        
        // If we couldn't determine the column type, fail explicitly rather than
        // risk creating an incompatible foreign key column
        throw new \RuntimeException(
            "Unable to determine the column type for users.id. " .
            "Please ensure the users table exists before running this migration."
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('friends_users');
    }
};
