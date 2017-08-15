<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\PaymentContext\Tests\Unit\Domain;

use WMDE\Fundraising\Frontend\PaymentContext\Domain\LessSimpleTransferCodeGenerator;

/**
 * @covers \WMDE\Fundraising\Frontend\PaymentContext\Domain\LessSimpleTransferCodeGenerator
 *
 * @licence GNU GPL v2+
 */
class LessSimpleTransferCodeGeneratorTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider characterAndCodeProvider
	 */
	public function testGenerateBankTransferCode( string $expectedCode, string $usedCharacters ): void {
		$generator = new LessSimpleTransferCodeGenerator(
			$this->newFixedCharacterGenerator( $usedCharacters )
		);

		$this->assertSame( $expectedCode, $generator->generateTransferCode() );
	}

	public function characterAndCodeProvider(): iterable {
		yield [ 'ABCD-EFGH-55', 'ABCDEFGHJKLMNOPQRSTUVWXYZ' ];
		yield [ 'AAAA-AAAA-99', 'AAAAAAAAAAAAAAAAAAAAAAAAA' ];
		yield [ 'QAQA-QAQA-E6', 'QAQAQAQAQAQAQAQAQAQAQAQAQ' ];
	}

	private function newFixedCharacterGenerator( string $characters ): \Generator {
		yield from str_split( $characters );
	}

}
