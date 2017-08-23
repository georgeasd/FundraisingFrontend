<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\PaymentContext\Domain;

use RuntimeException;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\Iban;

/**
 * @licence GNU GPL v2+
 * @author Christoph Fischer < christoph.fischer@wikimedia.de >
 */
class BankDataConverter {

	public function __construct() {
		lut_init();
	}

	/**
	 * @param string $account
	 * @param string $bankCode
	 * @return BankData
	 * @throws RuntimeException
	 */
	public function getBankDataFromAccountData( string $account, string $bankCode ): BankData {
		$bankData = new BankData();
		$iban = iban_gen( $bankCode, $account );

		if ( !$iban ) {
			throw new RuntimeException( 'Could not get IBAN' );
		}

		$bankData->setIban( new Iban( $iban ) );
		$bankData->setBic( iban2bic( $bankData->getIban()->toString() ) );

		$bankData->setAccount( $account );
		$bankData->setBankCode( $bankCode );
		$bankData->setBankName( $this->bankNameFromBankCode( $bankData->getBankCode() ) );
		$bankData->freeze()->assertNoNullFields();

		return $bankData;
	}

	/**
	 * @param Iban $iban
	 * @return BankData
	 * @throws \InvalidArgumentException
	 */
	public function getBankDataFromIban( Iban $iban ): BankData {
		if ( !$this->validateIban( $iban ) ) {
			throw new \InvalidArgumentException( 'Provided IBAN should be valid' );
		}

		$bankData = new BankData();
		$bankData->setIban( $iban );

		if ( $iban->getCountryCode() === 'DE' ) {
			$bankData->setBic( iban2bic( $iban->toString() ) );

			$bankData->setAccount( $iban->accountNrFromDeIban() );
			$bankData->setBankCode( $iban->bankCodeFromDeIban() );
			$bankData->setBankName( $this->bankNameFromBankCode( $bankData->getBankCode() ) );
		} else {
			$bankData->setBic( '' );

			$bankData->setAccount( '' );
			$bankData->setBankCode( '' );
			$bankData->setBankName( '' );
		}
		$bankData->freeze()->assertNoNullFields();

		return $bankData;
	}

	private function bankNameFromBankCode( string $bankCode ): string {
		return utf8_encode( lut_name( $bankCode ) );
	}

	public function validateIban( Iban $iban ): bool {
		return iban_check( $iban->toString() ) > 0;
	}
}
