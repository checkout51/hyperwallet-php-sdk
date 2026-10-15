<?php
namespace Hyperwallet\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;

class ClientTokenTest extends ModelTestCase {

    protected static function getModelName() {
        return 'AuthenticationToken';
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
