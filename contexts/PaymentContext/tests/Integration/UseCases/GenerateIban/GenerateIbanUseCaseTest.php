<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\PaymentContext\Tests\Integration\UseCases\GenerateIban;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\BankDataConverter;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\Frontend\PaymentContext\ResponseModel\IbanResponse;
use WMDE\Fundraising\Frontend\PaymentContext\UseCases\GenerateIban\GenerateIbanRequest;
use WMDE\Fundraising\Frontend\PaymentContext\UseCases\GenerateIban\GenerateIbanUseCase;
use WMDE\Fundraising\Frontend\Validation\KontoCheckIbanValidator;

/**
 * @covers \WMDE\Fundraising\Frontend\PaymentContext\UseCases\GenerateIban\GenerateIbanUseCase
 *
 * @licence GNU GPL v2+
 * @author Kai Nissen <kai.nissen@wikimedia.de>
 *
 * @requires extension konto_check
 */
class GenerateIbanUseCaseTest extends TestCase {

	public function testWhenValidBankAccountDataIsGiven_fullBankDataIsReturned(): void {
		$useCase = $this->newGenerateIbanUseCase();

		$bankData = new BankData();
		$bankData->setBic( 'HASPDEHHXXX' );
		$bankData->setIban( new Iban( 'DE76200505501015754243' ) );
		$bankData->setAccount( '1015754243' );
		$bankData->setBankCode( '20050550' );
		$bankData->setBankName( 'Hamburger Sparkasse' );
		$bankData->freeze()->assertNoNullFields();

		$this->assertEquals(
			IbanResponse::newSuccessResponse( $bankData ),
			$useCase->generateIban( new GenerateIbanRequest( '1015754243', '20050550' ) )
		);
	}

	public function testWhenInvalidBankAccountDataIsGiven_failureResponseIsReturned(): void {
		$useCase = $this->newGenerateIbanUseCase();

		$this->assertEquals(
			IbanResponse::newFailureResponse(),
			$useCase->generateIban( new GenerateIbanRequest( '1015754241', '20050550' ) )
		);
	}

	public function testWhenBlockedBankAccountDataIsGiven_failureResponseIsReturned(): void {
		$useCase = $this->newGenerateIbanUseCase();

		$this->assertEquals(
			IbanResponse::newFailureResponse(),
			$useCase->generateIban( new GenerateIbanRequest( '1194700', '10020500' ) )
		);
	}

	private function newGenerateIbanUseCase(): GenerateIbanUseCase {
		$bankDataConverter = new BankDataConverter();
		return new GenerateIbanUseCase(
			$bankDataConverter,
			new KontoCheckIbanValidator( $bankDataConverter, [ 'DE33100205000001194700' ] )
		);
	}
}
