<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFriendsUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('friends_users', function (Blueprint $table) {
            // Create foreign key columns that match the users.id definition exactly
            $this->createMatchingColumn($table, 'friend_id');
            $this->createMatchingColumn($table, 'user_id');
            
            $table->string('status');

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('friend_id')->references('id')->on('users');

            $table->primary(array('user_id', 'friend_id'));
        });
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
                // Example: id char(26) or id varchar(255)
                if ($result && preg_match("/{$columnName}\s+(?:var)?char\((\d+)\)/i", $result->sql, $matches)) {
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
                    
                    // Match the exact column definition including length
                    switch (strtolower($type)) {
                        case 'bigint':
                        case 'biginteger':
                            $table->unsignedBigInteger($columnName);
                            return;
                        case 'integer':
                        case 'int':
                            $table->unsignedInteger($columnName);
                            return;
                        case 'smallint':
                        case 'smallinteger':
                            $table->unsignedSmallInteger($columnName);
                            return;
                        case 'char':
                            // Preserve the exact length for char columns (e.g., ULID uses char(26))
                            $length = $length ?? 36;
                            $table->char($columnName, $length);
                            return;
                        case 'uuid':
                            $table->uuid($columnName);
                            return;
                        case 'string':
                        case 'varchar':
                            // Preserve the exact length for varchar columns
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
                
                // For char/varchar types, retrieve the actual length from the database
                // to ensure foreign key compatibility
                $length = null;
                if (in_array(strtolower($type), ['char', 'string', 'varchar'])) {
                    $length = $this->getColumnLength($connection, 'users', 'id');
                }
                
                switch (strtolower($type)) {
                    case 'bigint':
                    case 'biginteger':
                        $table->unsignedBigInteger($columnName);
                        return;
                    case 'integer':
                    case 'int':
                        $table->unsignedInteger($columnName);
                        return;
                    case 'smallint':
                    case 'smallinteger':
                        $table->unsignedSmallInteger($columnName);
                        return;
                    case 'char':
                        // Use exact length for char columns (e.g., ULID = 26, UUID = 36)
                        if ($length !== null) {
                            $table->char($columnName, $length);
                        } else {
                            // Safe default for UUID if length cannot be determined
                            $table->uuid($columnName);
                        }
                        return;
                    case 'uuid':
                    case 'guid':
                        $table->uuid($columnName);
                        return;
                    case 'string':
                    case 'varchar':
                        // Use exact length for varchar columns
                        if ($length !== null) {
                            $table->string($columnName, $length);
                        } else {
                            // Safe default if length cannot be determined
                            $table->string($columnName);
                        }
                        return;
                }
            }
        } catch (\Exception $e) {
            // Fall through to default
        }
        
        // Default fallback for modern Laravel apps
        $table->unsignedBigInteger($columnName);
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
}
