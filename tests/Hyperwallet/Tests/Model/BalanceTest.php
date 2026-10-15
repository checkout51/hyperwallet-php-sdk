<?php
namespace Hyperwallet\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;

class BalanceTest extends ModelTestCase {

    protected static function getModelName() {
        return 'Balance';
    }

    /**
     * @param string $property The property to look for
     */
    #[DataProvider('ignoredPropertiesProvider')]
    public function testGettersForIgnoredProperties($property) {
        $this->performGettersForIgnoredPropertiesTest($property);
    }

    /**
     * @param string $property The property to look for
     */
    #[DataProvider('propertiesProvider')]
    public function testGetterReturnValueIsSet($property) {
        $this->performGetterReturnValueIsSetTest($property);
    }

    /**
     * @param string $property The property to look for
     */
    #[DataProvider('propertiesProvider')]
    public function testGetterReturnValueIsNotSet($property) {
        $this->performGetterReturnValueIsNotSetTest($property);
    }
    
}
