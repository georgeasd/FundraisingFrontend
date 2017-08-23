<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\PaymentContext\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\BankDataConverter;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\BankDataLibraryInitializationException;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\Iban;

/**
 * @covers \WMDE\Fundraising\Frontend\PaymentContext\Domain\BankDataConverter
 *
 * @licence GNU GPL v2+
 * @author Christoph Fischer <christoph.fischer@wikimedia.de >
 *
 * @requires extension konto_check
 */
class BankDataConverterTest extends TestCase {

	public function testWhenUsingConfigLutPath_constructorCreatesConverter(): void {
		$this->assertInstanceOf( BankDataConverter::class, $this->newBankDataConverter() );
	}

	/**
	 * @dataProvider ibanTestProvider
	 */
	public function testWhenGivenInvalidIban_converterThrowsException( string $ibanToTest ): void {
		$bankConverter = $this->newBankDataConverter();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Provided IBAN should be valid' );
		$bankConverter->getBankDataFromIban( new Iban( $ibanToTest ) );
	}

	public function ibanTestProvider(): array {
		return [
			[ '' ],
			[ 'DE120105170648489892' ],
			[ 'DE1048489892' ],
			[ 'BE125005170648489890' ],
		];
	}

	/**
	 * @dataProvider ibanTestProvider
	 */
	public function testWhenGivenInvalidIban_validateIbanReturnsFalse( string $ibanToTest ): void {
		$bankConverter = $this->newBankDataConverter();

		$this->assertFalse( $bankConverter->validateIban( new Iban( $ibanToTest ) ) );
	}

	public function testWhenGivenValidIban_converterReturnsBankData(): void {
		$bankConverter = $this->newBankDataConverter();

		$bankData = new BankData();
		$bankData->setBankName( 'ING-DiBa' );
		$bankData->setAccount( '0648489890' );
		$bankData->setBankCode( '50010517' );
		$bankData->setBic( 'INGDDEFFXXX' );
		$bankData->setIban( new Iban( 'DE12500105170648489890' ) );
		$bankData->freeze();

		$this->assertEquals(
			$bankData,
			$bankConverter->getBankDataFromIban( new Iban( 'DE12500105170648489890' ) )
		);
	}

	public function testWhenGivenValidNonDEIban_converterReturnsIBAN(): void {
		$bankConverter = $this->newBankDataConverter();

		$bankData = new BankData();
		$bankData->setBankName( '' );
		$bankData->setAccount( '' );
		$bankData->setBankCode( '' );
		$bankData->setBic( '' );
		$bankData->setIban( new Iban( 'BE68844010370034' ) );
		$bankData->freeze();

		$this->assertEquals(
			$bankData,
			$bankConverter->getBankDataFromIban( new Iban( 'BE68844010370034' ) )
		);
	}

	public function testWhenGivenValidIban_validateIbanReturnsTrue(): void {
		$bankConverter = $this->newBankDataConverter();

		$this->assertTrue( $bankConverter->validateIban( new Iban( 'DE12500105170648489890' ) ) );
		$this->assertTrue( $bankConverter->validateIban( new Iban( 'BE68844010370034' ) ) );
	}

	/**
	 * @dataProvider accountTestProvider
	 */
	public function testWhenGivenInvalidAccountData_converterThrowsException( string $accountToTest, string $bankCodeToTest ): void {
		$bankConverter = $this->newBankDataConverter();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Could not get IBAN' );
		$bankConverter->getBankDataFromAccountData( $accountToTest, $bankCodeToTest );
	}

	public function accountTestProvider(): array {
		return [
			[ '', '' ],
			[ '0648489890', '' ],
			[ '0648489890', '12310517' ],
			[ '1234567890', '50010517' ],
			[ '', '50010517' ],
		];
	}

	public function testWhenGivenValidAccountData_converterReturnsBankData(): void {
		$bankConverter = $this->newBankDataConverter();

		$bankData = new BankData();
		$bankData->setBankName( 'ING-DiBa' );
		$bankData->setAccount( '0648489890' );
		$bankData->setBankCode( '50010517' );
		$bankData->setBic( 'INGDDEFFXXX' );
		$bankData->setIban( new Iban( 'DE12500105170648489890' ) );
		$bankData->freeze();

		$this->assertEquals(
			$bankData,
			$bankConverter->getBankDataFromAccountData( '0648489890', '50010517' )
		);
	}

	private function newBankDataConverter(): BankDataConverter {
		return new BankDataConverter();
	}

}
