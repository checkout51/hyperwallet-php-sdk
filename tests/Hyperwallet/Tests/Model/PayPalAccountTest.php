<?php
namespace Hyperwallet\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;

class PayPalAccountTest extends ModelTestCase {

    protected static function getModelName() {
        return 'PayPalAccount';
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
    #[DataProvider('notIgnoredPropertiesProvider')]
    public function testGettersAndSettersForNotIgnoredProperties($property) {
        $this->performGettersAndSettersForNotIgnoredPropertiesTest($property);
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

    /**
     * @param string $property The property to look for
     */
    #[DataProvider('notIgnoredPropertiesProvider')]
    public function testGetterAndSetterReturnValueIsSetIfValueIsProvidedAndDefaultIsSet($property) {
        $this->performGetterAndSetterReturnValueIsSetIfValueIsProvidedAndDefaultIsSetTest($property);
    }

    /**
     * @param string $property The property to look for
     */
    #[DataProvider('notIgnoredPropertiesProvider')]
    public function testGetterAndSetterReturnValueIsSetIfValueIsProvidedAndDefaultIsNotSet($property) {
        $this->performGetterAndSetterReturnValueIsSetIfValueIsProvidedAndDefaultIsNotSetTest($property);
    }

    /**
     * @param string $property The property to look for
     */
    #[DataProvider('notIgnoredPropertiesProvider')]
    public function testGetterAndSetterNullField($property) {
        $this->performGetterAndSetterNullFieldTest($property);
    }

    public function testTokenSetterTestWithValue() {
        $this->performTokenSetterTestWithValue();
    }

    public function testTokenSetterTestWithoutValue() {
        $this->performTokenSetterTestWithoutValue();
    }
}
