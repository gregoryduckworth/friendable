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
    protected $originalGrammar;
    
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
        $app->singleton('db.schema', function () {
            return $this->capsule->getConnection()->getSchemaBuilder();
        });
        Facade::setFacadeApplication($app);
        
        // Install custom SQLite grammar that preserves varchar lengths (Laravel 11+ compatibility)
        // This ensures that char(26) and string(50) are stored with their lengths
        $connection = $this->capsule->getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            // Load the grammar class from the migration file
            require_once __DIR__ . '/../database/migrations/create_friends_users_table.php';
            $this->originalGrammar = $connection->getSchemaGrammar();
            $connection->setSchemaGrammar(new \SQLiteGrammarWithLengths());
        }
    }
    
    protected function tearDown(): void
    {
        // Restore original grammar
        if (isset($this->originalGrammar) && $this->originalGrammar) {
            $connection = $this->capsule->getConnection();
            if ($connection) {
                $connection->setSchemaGrammar($this->originalGrammar);
            }
        }
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
        // Handle both quoted and unquoted column names
        $columnPattern = preg_quote($column, '/');
        if (preg_match("/[\"']?{$columnPattern}[\"']?\s+(\w+)(?:\((\d+)\))?/i", $result->sql, $matches)) {
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
        
        // Run the friends_users migration (anonymous class)
        $migration = require __DIR__ . '/../database/migrations/create_friends_users_table.php';
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
        
        // Run the friends_users migration (anonymous class)
        $migration = require __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration->up();
        
        // Verify the table was created
        $this->assertTrue(Capsule::schema()->hasTable('friends_users'));
        
        // Verify the columns exist
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'user_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'friend_id'));
        
        // Verify column types match users.id (varchar for UUID in Laravel 12+)
        $userIdType = $this->getColumnType('friends_users', 'user_id');
        $friendIdType = $this->getColumnType('friends_users', 'friend_id');
        $this->assertNotNull($userIdType, 'user_id column type should be detectable');
        $this->assertNotNull($friendIdType, 'friend_id column type should be detectable');
        // Laravel 12+ uses generic varchar for string types in SQLite
        $this->assertSame('varchar', $userIdType['type'], 'user_id should be varchar type');
        $this->assertSame('varchar', $friendIdType['type'], 'friend_id should be varchar type');
        // Verify length matches UUID (36 characters)
        $this->assertSame(36, $userIdType['length'], 'user_id should have length 36 for UUID');
        $this->assertSame(36, $friendIdType['length'], 'friend_id should have length 36 for UUID');
        
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
        
        // Run the friends_users migration (anonymous class)
        $migration = require __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration->up();
        
        // Verify the table was created
        $this->assertTrue(Capsule::schema()->hasTable('friends_users'));
        
        // Verify the columns exist
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'user_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'friend_id'));
        
        // Verify column types match users.id (varchar for ULID in Laravel 12+)
        $userIdType = $this->getColumnType('friends_users', 'user_id');
        $friendIdType = $this->getColumnType('friends_users', 'friend_id');
        $this->assertNotNull($userIdType, 'user_id column type should be detectable');
        $this->assertNotNull($friendIdType, 'friend_id column type should be detectable');
        // Laravel 12+ uses generic varchar for string types in SQLite
        $this->assertSame('varchar', $userIdType['type'], 'user_id should be varchar type');
        $this->assertSame('varchar', $friendIdType['type'], 'friend_id should be varchar type');
        // Verify length matches ULID (26 characters)
        $this->assertSame(26, $userIdType['length'], 'user_id should have length 26 for ULID');
        $this->assertSame(26, $friendIdType['length'], 'friend_id should have length 26 for ULID');
        
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
        
        // Run the friends_users migration (anonymous class)
        $migration = require __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration->up();
        
        // Verify the table was created
        $this->assertTrue(Capsule::schema()->hasTable('friends_users'));
        
        // Verify the columns exist
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'user_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'friend_id'));
        
        // Verify column types match users.id (varchar for custom string in Laravel 12+)
        $userIdType = $this->getColumnType('friends_users', 'user_id');
        $friendIdType = $this->getColumnType('friends_users', 'friend_id');
        $this->assertNotNull($userIdType, 'user_id column type should be detectable');
        $this->assertNotNull($friendIdType, 'friend_id column type should be detectable');
        // Laravel 12+ uses generic varchar for string types in SQLite (without explicit length)
        $this->assertSame('varchar', $userIdType['type'], 'user_id should be varchar type');
        $this->assertSame('varchar', $friendIdType['type'], 'friend_id should be varchar type');
        // Verify length matches custom string (50 characters)
        $this->assertSame(50, $userIdType['length'], 'user_id should have length 50 for custom string');
        $this->assertSame(50, $friendIdType['length'], 'friend_id should have length 50 for custom string');
        
        // Clean up
        $migration->down();
        Capsule::schema()->dropIfExists('users');
    }
    
    /**
     * Test migration works with signed integer user ID (non-standard but valid)
     */
    public function testMigrationWithSignedIntegerUserId()
    {
        // Create users table with signed integer ID (without unsigned)
        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        
        // Run the friends_users migration (anonymous class)
        $migration = require __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration->up();
        
        // Verify the table was created
        $this->assertTrue(Capsule::schema()->hasTable('friends_users'));
        
        // Verify the columns exist
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'user_id'));
        $this->assertTrue(Capsule::schema()->hasColumn('friends_users', 'friend_id'));
        
        // Verify column types match users.id (integer for SQLite)
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
     * Test that foreign key constraints are actually enforced
     * This verifies the column types are compatible at the database level
     */
    public function testForeignKeyConstraintsAreEnforced()
    {
        // Enable foreign key enforcement in SQLite
        $connection = $this->capsule->getConnection();
        $connection->statement('PRAGMA foreign_keys = ON');
        
        // Create users table with bigInteger ID
        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        
        // Run the friends_users migration (anonymous class)
        $migration = require __DIR__ . '/../database/migrations/create_friends_users_table.php';
        $migration->up();
        
        // Create a test user
        $userId = $connection->table('users')->insertGetId([
            'name' => 'Test User',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        // Valid insert: both user_id and friend_id reference existing users
        $connection->table('friends_users')->insert([
            'user_id' => $userId,
            'friend_id' => $userId,
            'status' => 'pending',
        ]);
        $this->assertSame(1, $connection->table('friends_users')->count(), 'Valid foreign key reference should succeed');
        
        // Invalid insert: friend_id references non-existent user
        // This should fail if foreign keys are properly enforced
        $caught = false;
        try {
            $connection->table('friends_users')->insert([
                'user_id' => $userId,
                'friend_id' => 99999,
                'status' => 'pending',
            ]);
        } catch (\Exception $e) {
            $caught = true;
            $this->assertStringContainsString('foreign key', strtolower($e->getMessage()), 'Exception should mention foreign key constraint');
        }
        $this->assertTrue($caught, 'Insert with invalid foreign key should fail');
        
        // Verify only the valid insert succeeded
        $this->assertSame(1, $connection->table('friends_users')->count(), 'Only valid inserts should be in the table');
        
        // Clean up
        $migration->down();
        Capsule::schema()->dropIfExists('users');
    }
}
