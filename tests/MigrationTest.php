<?php

namespace GregoryDuckworth\Friendable\Test;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
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
        
        // Bootstrap facades to support Schema:: calls in migration
        $app = new \Illuminate\Container\Container();
        $app->singleton('db', function () {
            return $this->capsule->getDatabaseManager();
        });
        Facade::setFacadeApplication($app);
    }
    
    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        $this->capsule->getConnection()->disconnect();
        parent::tearDown();
    }
    
    /**
     * Helper to get the column type from the database
     */
    protected function getColumnType($table, $column)
    {
        $connection = $this->capsule->getConnection();
        $result = $connection->selectOne(
            "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
            [$table]
        );
        
        if (!$result) {
            return null;
        }
        
        // Parse the CREATE TABLE statement
        // Match patterns like: column_name TYPE or column_name TYPE(length)
        if (preg_match("/{$column}\s+(\w+)(?:\((\d+)\))?/i", $result->sql, $matches)) {
            $type = strtolower($matches[1]);
            $length = isset($matches[2]) ? (int)$matches[2] : null;
            return ['type' => $type, 'length' => $length];
        }
        
        return null;
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
        
        // Verify column types match users.id (bigint/integer for SQLite)
        $userIdType = $this->getColumnType('friends_users', 'user_id');
        $friendIdType = $this->getColumnType('friends_users', 'friend_id');
        $this->assertNotNull($userIdType, 'user_id column type should be detectable');
        $this->assertNotNull($friendIdType, 'friend_id column type should be detectable');
        $this->assertSame('integer', $userIdType['type'], 'user_id should be integer type');
        $this->assertSame('integer', $friendIdType['type'], 'friend_id should be integer type');
        
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
        
        // Verify column types match users.id (char(36) for UUID in SQLite)
        $userIdType = $this->getColumnType('friends_users', 'user_id');
        $friendIdType = $this->getColumnType('friends_users', 'friend_id');
        $this->assertNotNull($userIdType, 'user_id column type should be detectable');
        $this->assertNotNull($friendIdType, 'friend_id column type should be detectable');
        $this->assertSame('char', $userIdType['type'], 'user_id should be char type for UUID');
        $this->assertSame('char', $friendIdType['type'], 'friend_id should be char type for UUID');
        $this->assertSame(36, $userIdType['length'], 'user_id should be char(36) for UUID');
        $this->assertSame(36, $friendIdType['length'], 'friend_id should be char(36) for UUID');
        
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
        
        // Verify column types match users.id (char(26) for ULID)
        $userIdType = $this->getColumnType('friends_users', 'user_id');
        $friendIdType = $this->getColumnType('friends_users', 'friend_id');
        $this->assertNotNull($userIdType, 'user_id column type should be detectable');
        $this->assertNotNull($friendIdType, 'friend_id column type should be detectable');
        $this->assertSame('char', $userIdType['type'], 'user_id should be char type for ULID');
        $this->assertSame('char', $friendIdType['type'], 'friend_id should be char type for ULID');
        $this->assertSame(26, $userIdType['length'], 'user_id should be char(26) for ULID');
        $this->assertSame(26, $friendIdType['length'], 'friend_id should be char(26) for ULID');
        
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
        
        // Verify column types match users.id (varchar(50) for custom string)
        $userIdType = $this->getColumnType('friends_users', 'user_id');
        $friendIdType = $this->getColumnType('friends_users', 'friend_id');
        $this->assertNotNull($userIdType, 'user_id column type should be detectable');
        $this->assertNotNull($friendIdType, 'friend_id column type should be detectable');
        $this->assertSame('varchar', $userIdType['type'], 'user_id should be varchar type for custom string');
        $this->assertSame('varchar', $friendIdType['type'], 'friend_id should be varchar type for custom string');
        $this->assertSame(50, $userIdType['length'], 'user_id should be varchar(50) for custom string');
        $this->assertSame(50, $friendIdType['length'], 'friend_id should be varchar(50) for custom string');
        
        // Clean up
        $migration->down();
        Capsule::schema()->dropIfExists('users');
    }
}
