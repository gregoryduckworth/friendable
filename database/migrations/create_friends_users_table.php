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
        
        // Fallback: use getColumnType (Laravel 9.0-9.5) or default to unsignedBigInteger
        try {
            if (method_exists($schemaBuilder, 'getColumnType')) {
                $type = $schemaBuilder->getColumnType('users', 'id');
                
                // Note: This legacy path cannot preserve length information,
                // so it uses Laravel's defaults for each type
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
                    case 'uuid':
                    case 'guid':
                        $table->uuid($columnName);
                        return;
                    case 'string':
                    case 'varchar':
                        $table->string($columnName);
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
