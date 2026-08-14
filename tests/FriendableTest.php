<?php

use PHPUnit\Framework\TestCase;

/**
 * Gather the appropriate trait
 */
class TestingTraitStub
{
    use GregoryDuckworth\Friendable\Traits\Friendable;
}

/**
 *
 */
class TraitFriendableTest extends TestCase
{
    // Trait within test scope
    public $trait;

    /**
     * Setup the trait
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->trait = new TestingTraitStub;
    }

    /**
     * Check that the trait is in use
     */
    public function testTraitUsable()
    {
        $traits = class_uses($this->trait);

        $this->assertContains('GregoryDuckworth\Friendable\Traits\Friendable', $traits);
    }

}
