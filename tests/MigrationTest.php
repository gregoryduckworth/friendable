<?php

namespace GregoryDuckworth\Friendable\Test;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * Test migration compatibility with different user ID types
 */
class MigrationTest extends TestCase
{
    protected $capsule;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up an in-memory SQLite database for testing
        $this->capsule = new Capsule;
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }
    
    protected function tearDown(): void
    {
        $this->capsule->getConnection()->disconnect();
        parent::tearDown();
    }
    
    /**
     * Test migration works with bigInteger user ID (default Laravel)
     */
    public function testMigrationWithBigIntegerUserId()
    {
        // Create users table with bigInteger ID
        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->id(); // bigIncrements
            $table->string('name');
            $table->timestamps();
        });
        
        // Run the friends_users migration
        require_once __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration = new \CreateFriendsUsersTable();
        $migration->up();
        
        // Verify the table was created
        $this->assertTrue(Capsule::schema()->hasTable('friends_users'));
        
        // Verify the columns exist
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'user_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'friend_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'status'));
        
        // Clean up
        $migration->down();
        Capsule::schema()->dropIfExists('users');
    }
    
    /**
     * Test migration works with UUID user ID
     */
    public function testMigrationWithUuidUserId()
    {
        // Create users table with UUID ID
        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        
        // Run the friends_users migration
        require_once __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration = new \CreateFriendsUsersTable();
        $migration->up();
        
        // Verify the table was created
        $this->assertTrue(Capsule::schema()->hasTable('friends_users'));
        
        // Verify the columns exist
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'user_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'friend_id'));
        
        // Clean up
        $migration->down();
        Capsule::schema()->dropIfExists('users');
    }
    
    /**
     * Test migration works with ULID user ID (char(26))
     */
    public function testMigrationWithUlidUserId()
    {
        // Create users table with ULID ID (char(26))
        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name');
            $table->timestamps();
        });
        
        // Run the friends_users migration
        require_once __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration = new \CreateFriendsUsersTable();
        $migration->up();
        
        // Verify the table was created
        $this->assertTrue(Capsule::schema()->hasTable('friends_users'));
        
        // Verify the columns exist
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'user_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'friend_id'));
        
        // Clean up
        $migration->down();
        Capsule::schema()->dropIfExists('users');
    }
    
    /**
     * Test migration works with custom string user ID
     */
    public function testMigrationWithCustomStringUserId()
    {
        // Create users table with custom string ID
        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('name');
            $table->timestamps();
        });
        
        // Run the friends_users migration
        require_once __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration = new \CreateFriendsUsersTable();
        $migration->up();
        
        // Verify the table was created
        $this->assertTrue(Capsule::schema()->hasTable('friends_users'));
        
        // Verify the columns exist
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'user_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'friend_id'));
        
        // Clean up
        $migration->down();
        Capsule::schema()->dropIfExists('users');
    }
}
