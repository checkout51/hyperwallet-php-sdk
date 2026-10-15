<?php
namespace Hyperwallet\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;

class PaymentTest extends ModelTestCase {

    protected static function getModelName() {
        return 'Payment';
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
        if ($property === 'description') {
            // skip testing invalid and deprecated field
            $this->assertEquals($property, 'description');
            return;
        }

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
        if ($property === 'description') {
            // skip testing invalid and deprecated field
            $this->assertEquals($property, 'description');
            return;
        }

        $this->performGetterAndSetterReturnValueIsSetIfValueIsProvidedAndDefaultIsSetTest($property);
    }

    /**
     * @param string $property The property to look for
     */
    #[DataProvider('notIgnoredPropertiesProvider')]
    public function testGetterAndSetterReturnValueIsSetIfValueIsProvidedAndDefaultIsNotSet($property) {
        if ($property === 'description') {
            // skip testing invalid and deprecated field
            $this->assertEquals($property, 'description');
            return;
        }

        $this->performGetterAndSetterReturnValueIsSetIfValueIsProvidedAndDefaultIsNotSetTest($property);
    }

    /**
     * @param string $property The property to look for
     */
    #[DataProvider('notIgnoredPropertiesProvider')]
    public function testGetterAndSetterNullField($property) {
        if ($property === 'description') {
            // skip testing invalid and deprecated field
            $this->assertEquals($property, 'description');
            return;
        }

        $this->performGetterAndSetterNullFieldTest($property);
    }

    public function testTokenSetterTestWithValue() {
        $this->performTokenSetterTestWithValue();
    }

    public function testTokenSetterTestWithoutValue() {
        $this->performTokenSetterTestWithoutValue();
    }

}
