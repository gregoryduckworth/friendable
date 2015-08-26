<?php

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
class TraitFriendableTest extends PHPUnit_Framework_TestCase
{
    // Trait within test scope
    public $trait;

    /**
     * Setup the trait
     */
    public function setUp()
    {
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
