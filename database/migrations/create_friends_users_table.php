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
            // Dynamically determine the column type based on users.id to maintain compatibility
            // with apps that use integer, bigint, UUID, ULID, or string primary keys
            $userIdColumnType = $this->getUserIdColumnType();
            
            // Map the users.id column type to the appropriate Blueprint method
            switch ($userIdColumnType) {
                case 'bigint':
                    $table->unsignedBigInteger('friend_id');
                    $table->unsignedBigInteger('user_id');
                    break;
                case 'uuid':
                case 'char':
                case 'guid':
                    $table->uuid('friend_id');
                    $table->uuid('user_id');
                    break;
                case 'ulid':
                    $table->ulid('friend_id');
                    $table->ulid('user_id');
                    break;
                case 'string':
                case 'varchar':
                    $table->string('friend_id');
                    $table->string('user_id');
                    break;
                default:
                    // Fallback for integer and other numeric types
                    $table->unsignedInteger('friend_id');
                    $table->unsignedInteger('user_id');
                    break;
            }
            
            $table->string('status');

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('friend_id')->references('id')->on('users');

            $table->primary(array('user_id', 'friend_id'));
        });
    }
    
    /**
     * Determine the column type of the users.id column.
     *
     * @return string
     */
    protected function getUserIdColumnType()
    {
        $connection = Schema::connection(null);
        $userTable = $connection->getColumnType('users', 'id');
        
        return $userTable;
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
