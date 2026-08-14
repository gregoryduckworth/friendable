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
            // with apps that still use integer IDs from older Laravel versions
            $userIdColumnType = $this->getUserIdColumnType();
            
            if ($userIdColumnType === 'bigint') {
                $table->unsignedBigInteger('friend_id');
                $table->unsignedBigInteger('user_id');
            } else {
                $table->unsignedInteger('friend_id');
                $table->unsignedInteger('user_id');
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
