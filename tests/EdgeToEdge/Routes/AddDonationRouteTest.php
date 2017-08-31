<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use Silex\Application;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;
use WMDE\Fundraising\Entities\Donation;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Infrastructure\PageViewTracker;
use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;
use WMDE\Fundraising\Frontend\Tests\Fixtures\FixedTokenGenerator;
use WMDE\Fundraising\Frontend\PaymentContext\DataAccess\Sofort\Transfer\Client as SofortClient;
use WMDE\Fundraising\Frontend\PaymentContext\DataAccess\Sofort\Transfer\Response as SofortResponse;

/**
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 *
 * @requires extension konto_check
 */
class AddDonationRouteTest extends WebRouteTestCase {

	const SOME_TOKEN = 'SomeToken';

	public function testGivenValidRequest_donationGetsPersisted(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {

			$client->setServerParameter( 'HTTP_REFERER', 'https://en.wikipedia.org/wiki/Karla_Kennichnich' );
			$client->followRedirects( false );

			$client->request(
				'POST',
				'/donation/add',
				$this->newValidFormInput()
			);

			$this->assertIsExpectedDonation( $this->getDonationFromDatabase( $factory ) );
		} );
	}

	public function testWhenDonationGetsPersisted_timestampIsStoredInCookie(): void {
		$client = $this->createClient();
		$client->followRedirects( true );
		$client->request(
			'POST',
			'/donation/add',
			$this->newValidFormInput()
		);

		$cookie = $client->getCookieJar()->get( 'donation_timestamp' );
		$this->assertNotNull( $cookie );
		$donationTimestamp = new \DateTime( $cookie->getValue() );
		$this->assertEquals( time(), $donationTimestamp->getTimestamp(), 'Timestamp should be not more than 5 seconds old', 5.0 );
	}

	public function testWhenMultipleDonationFormSubmissions_requestGetsRejected(): void {
		$client = $this->createClient();
		$client->getCookieJar()->set( new Cookie( 'donation_timestamp', $this->getPastTimestamp() ) );

		$client->request(
			'POST',
			'/donation/add',
			$this->newValidFormInput()
		);

		$this->assertContains( 'donation_rejected_limit', $client->getResponse()->getContent() );
	}

	public function testWhenMultipleDonationsInAccordanceToTimeLimit_requestIsNotRejected(): void {
		$client = $this->createClient();
		$client->getCookieJar()->set(
			new Cookie(
				'donation_timestamp',
				$this->getPastTimestamp( 'PT35M' )
			)
		);

		$client->request(
			'POST',
			'/donation/add',
			$this->newValidFormInput()
		);

		$this->assertNotContains( 'donation_rejected_limit', $client->getResponse()->getContent() );
	}

	private function getPastTimestamp( string $interval = 'PT10S' ): string {
		return ( new \DateTime() )->sub( new \DateInterval( $interval ) )->format( 'Y-m-d H:i:s' );
	}

	private function newValidFormInput(): array {
		return [
			'betrag' => '5,51',
			'zahlweise' => 'BEZ',
			'periode' => 0,
			'iban' => 'DE12500105170648489890',
			'bic' => 'INGDDEFFXXX',
			'konto' => '0648489890',
			'blz' => '50010517',
			'bankname' => 'ING-DiBa',
			'addressType' => 'person',
			'salutation' => 'Frau',
			'title' => 'Prof. Dr.',
			'company' => '',
			'firstName' => 'Karla',
			'lastName' => 'Kennichnich',
			'street' => 'Lehmgasse 12',
			'postcode' => '12345',
			'city' => 'Einort',
			'country' => 'DE',
			'email' => 'karla@kennichnich.de',
			'info' => '1',
			'piwik_campaign' => 'test',
			'piwik_kwd' => 'gelb',
			'impCount' => '3',
			'bImpCount' => '1',
			'layout' => 'Default',
			'color' => 'blue',
			'skin' => 'default',
		];
	}

	private function assertIsExpectedDonation( Donation $donation ): void {
		$data = $donation->getDecodedData();
		$this->assertSame( '5.51', $donation->getAmount() );
		$this->assertSame( 'BEZ', $donation->getPaymentType() );
		$this->assertSame( 0, $donation->getPaymentIntervalInMonths() );
		$this->assertSame( 'DE12500105170648489890', $data['iban'] );
		$this->assertSame( 'INGDDEFFXXX', $data['bic'] );
		$this->assertSame( '0648489890', $data['konto'] );
		$this->assertSame( '50010517', $data['blz'] );
		$this->assertSame( 'ING-DiBa', $data['bankname'] );
		$this->assertSame( 'person', $data['adresstyp'] );
		$this->assertSame( 'Frau', $data['anrede'] );
		$this->assertSame( 'Prof. Dr.', $data['titel'] );
		$this->assertSame( '', $data['firma'] );
		$this->assertSame( 'Karla', $data['vorname'] );
		$this->assertSame( 'Kennichnich', $data['nachname'] );
		$this->assertSame( 'Prof. Dr. Karla Kennichnich', $donation->getDonorFullName() );
		$this->assertSame( 'Lehmgasse 12', $data['strasse'] );
		$this->assertSame( '12345', $data['plz'] );
		$this->assertSame( 'Einort', $data['ort'] );
		$this->assertSame( 'Einort', $donation->getDonorCity() );
		$this->assertSame( 'DE', $data['country'] );
		$this->assertSame( 'karla@kennichnich.de', $data['email'] );
		$this->assertSame( 'karla@kennichnich.de', $donation->getDonorEmail() );
		$this->assertSame( 'test/gelb', $data['tracking'] );
		$this->assertSame( 3, $data['impCount'] );
		$this->assertSame( 1, $data['bImpCount'] );
		$this->assertSame( '', $data['layout'] );
		$this->assertSame( '', $data['color'] );
		$this->assertSame( '', $data['skin'] );
		$this->assertSame( 'en.wikipedia.org', $data['source'] );
		$this->assertSame( 'N', $donation->getStatus() );
		$this->assertTrue( $donation->getDonorOptsIntoNewsletter() );
	}

	public function testGivenValidRequest_confirmationPageContainsEnteredData(): void {
		$client = $this->createClient();
		$client->request(
			'POST',
			'/donation/add',
			$this->newValidFormInput()
		);
		$client->followRedirect();

		$response = $client->getResponse()->getContent();

		$this->assertContains( '5,51 €', $response );
		$this->assertContains( 'donation.interval: 0', $response );
		$this->assertContains( 'DE12500105170648489890', $response );
		$this->assertContains( 'INGDDEFFXXX', $response );
		$this->assertContains( 'ING-DiBa', $response );
		$this->assertContains( 'Prof. Dr. Karla Kennichnich', $response );
		$this->assertContains( 'Lehmgasse 12', $response );
		$this->assertContains( '<span id="confirm-postcode">12345</span> <span id="confirm-city">Einort</span>', $response );
		$this->assertContains( 'karla@kennichnich.de', $response );
		$this->assertContains( 'send-info', $response );
	}

	public function testGivenValidBankTransferRequest_donationGetsPersisted(): void {
		/**
		 * @var FunFunFactory
		 */
		$factory = null;

		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {

			$client->setServerParameter( 'HTTP_REFERER', 'https://en.wikipedia.org/wiki/Karla_Kennichnich' );
			$client->followRedirects( false );

			$client->request(
				'POST',
				'/donation/add',
				$this->newValidBankTransferInput()
			);

			$donation = $this->getDonationFromDatabase( $factory );

			$data = $donation->getDecodedData();
			$this->assertSame( '12.34', $donation->getAmount() );
			$this->assertSame( 'UEB', $donation->getPaymentType() );
			$this->assertSame( 0, $donation->getPaymentIntervalInMonths() );
			$this->assertSame( 'person', $data['adresstyp'] );
			$this->assertSame( 'Frau', $data['anrede'] );
			$this->assertSame( 'Prof. Dr.', $data['titel'] );
			$this->assertSame( '', $data['firma'] );
			$this->assertSame( 'Karla', $data['vorname'] );
			$this->assertSame( 'Kennichnich', $data['nachname'] );
			$this->assertSame( 'Prof. Dr. Karla Kennichnich', $donation->getDonorFullName() );
			$this->assertSame( 'Lehmgasse 12', $data['strasse'] );
			$this->assertSame( '12345', $data['plz'] );
			$this->assertSame( 'Einort', $data['ort'] );
			$this->assertSame( 'Einort', $donation->getDonorCity() );
			$this->assertSame( 'DE', $data['country'] );
			$this->assertSame( 'karla@kennichnich.de', $data['email'] );
			$this->assertSame( 'karla@kennichnich.de', $donation->getDonorEmail() );
			$this->assertSame( 'test/gelb', $data['tracking'] );
			$this->assertSame( 3, $data['impCount'] );
			$this->assertSame( 1, $data['bImpCount'] );
			$this->assertSame( '', $data['layout'] );
			$this->assertSame( '', $data['color'] );
			$this->assertSame( '', $data['skin'] );
			$this->assertSame( 'en.wikipedia.org', $data['source'] );
			$this->assertSame( true, $donation->getDonorOptsIntoNewsletter() );

			$this->assertSame( 'Z', $donation->getStatus() );
			$this->assertRegExp( '/W-Q-[A-Z]{6}-[A-Z]/', $donation->getBankTransferCode() );
		} );
	}

	private function newValidBankTransferInput(): array {
		return [
			'betrag' => '12,34',
			'zahlweise' => 'UEB',
			'periode' => 0,
			'addressType' => 'person',
			'salutation' => 'Frau',
			'title' => 'Prof. Dr.',
			'company' => '',
			'firstName' => 'Karla',
			'lastName' => 'Kennichnich',
			'street' => 'Lehmgasse 12',
			'postcode' => '12345',
			'city' => 'Einort',
			'country' => 'DE',
			'email' => 'karla@kennichnich.de',
			'info' => '1',
			'piwik_campaign' => 'test',
			'piwik_kwd' => 'gelb',
			'impCount' => '3',
			'bImpCount' => '1',
			'layout' => 'Default',
			'color' => 'blue',
			'skin' => 'default',
		];
	}

	public function testGivenComplementableBankData_donationStillGetsPersisted(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {

			$client->setServerParameter( 'HTTP_REFERER', 'https://en.wikipedia.org/wiki/Karla_Kennichnich' );
			$client->followRedirects( false );

			$client->request(
				'POST',
				'/donation/add',
				$this->newComplementableFormInput()
			);

			$donation = $this->getDonationFromDatabase( $factory );

			$data = $donation->getDecodedData();
			$this->assertSame( 'DE12500105170648489890', $data['iban'] );
			$this->assertSame( 'INGDDEFFXXX', $data['bic'] );
			$this->assertSame( '0648489890', $data['konto'] );
			$this->assertSame( '50010517', $data['blz'] );
			$this->assertSame( 'ING-DiBa', $data['bankname'] );
		} );
	}

	private function newComplementableFormInput(): array {
		return [
			'betrag' => '5,51',
			'zahlweise' => 'BEZ',
			'periode' => 0,
			'iban' => 'DE12500105170648489890',
			'addressType' => 'person',
			'salutation' => 'Frau',
			'title' => 'Prof. Dr.',
			'firstName' => 'Karla',
			'lastName' => 'Kennichnich',
			'street' => 'Lehmgasse 12',
			'postcode' => '12345',
			'city' => 'Einort',
			'country' => 'DE',
			'email' => 'karla@kennichnich.de',
		];
	}

	private function getDonationFromDatabase( FunFunFactory $factory ): Donation {
		$donationRepo = $factory->getEntityManager()->getRepository( Donation::class );
		$donation = $donationRepo->find( 1 );
		$this->assertInstanceOf( Donation::class, $donation );
		return $donation;
	}

	public function testGivenValidPayPalData_redirectsToPayPal(): void {
		$client = $this->createClient();
		$client->followRedirects( false );

		$client->request(
			'POST',
			'/donation/add',
			$this->newValidPayPalInput()
		);

		$response = $client->getResponse();
		$this->assertSame( Response::HTTP_FOUND, $response->getStatusCode() );
		$this->assertContains( 'sandbox.paypal.com', $response->getContent() );
	}

	public function testWhenRedirectingToPayPal_translatedItemNameIsPassed(): void {
		$client = $this->createClient();
		$client->followRedirects( false );

		$client->request(
			'POST',
			'/donation/add',
			$this->newValidPayPalInput()
		);

		$response = $client->getResponse();
		$this->assertSame( Response::HTTP_FOUND, $response->getStatusCode() );
		$this->assertContains( 'item_name=item_name_donation', $response->getContent() );
	}

	private function newValidPayPalInput(): array {
		return [
			'betrag' => '12,34',
			'zahlweise' => 'PPL',
			'periode' => 3,
			'addressType' => 'anonym',
		];
	}

	public function testGivenValidCreditCardData_showsIframeEmbeddingPage(): void {
		$client = $this->createClient();
		$client->request(
			'POST',
			'/donation/add',
			$this->newValidCreditCardInput()
		);

		$response = $client->getResponse();
		$this->assertSame( Response::HTTP_FOUND, $response->getStatusCode() );
		$this->assertContains( 'amount=1234', $response->getContent() );
		$this->assertContains( 'Redirecting to', $response->getContent() );
		$this->assertContains( 'thatother.paymentprovider.com', $response->getContent() );
	}

	public function testValidSofortInput_savesDonationAndRedirectsTo3rdPartyPage(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {
			$response = new SofortResponse();
			$response->setPaymentUrl( 'https://bankingpin.please' );

			$sofortClient = $this->createMock( SofortClient::class );
			$sofortClient
				->method( 'get' )
				->willReturn( $response );
			$factory->setSofortClient( $sofortClient );


			$client->followRedirects( false );
			$client->request(
				'POST',
				'/donation/add',
				$this->newValidSofortInput()
			);

			$donation = $this->getDonationFromDatabase( $factory );

			$this->assertSame( 'Z', $donation->getStatus() );
			$this->assertRegExp( '/W-Q-[A-Z]{6}-[A-Z]/', $donation->getBankTransferCode() );

			$this->assertTrue( $client->getResponse()->isRedirect( 'https://bankingpin.please' ) );
		} );
	}

	private function newValidCreditCardInput(): array {
		return [
			'betrag' => '12,34',
			'zahlweise' => 'MCP',
			'periode' => 3,
			'addressType' => 'anonym',
		];
	}

	private function newValidSofortInput(): array {
		return [
			'betrag' => '100,00',
			'zahlweise' => 'SUB',
			'periode' => 0,
			'addressType' => 'anonym',
		];
	}

	public function testGivenInvalidRequest_formIsReloadedAndPrefilled(): void {
		$client = $this->createClient();
		$client->request(
			'POST',
			'/donation/add',
			$this->newInvalidFormInput()
		);

		$response = $client->getResponse()->getContent();

		$this->assertContains( 'Amount: 0,00', $response );
		$this->assertContains( 'Payment type: BEZ', $response );
		$this->assertContains( 'Interval: 3', $response );
		$this->assertContains( 'IBAN: DE12500105170648489890', $response );
		$this->assertContains( 'BIC: INGDDEFFXXX', $response );
		$this->assertContains( 'Bank name: ING-DiBa', $response );
		$this->assertContains( 'Address type: person', $response );
		$this->assertContains( 'Salutation: Frau', $response );
		$this->assertContains( 'Title: Prof. Dr.', $response );
		$this->assertContains( 'Company: ', $response );
		$this->assertContains( 'First name: Karla', $response );
		$this->assertContains( 'Last name: Kennichnich', $response );
		$this->assertContains( 'Street: Lehmgasse 12', $response );
		$this->assertContains( 'Postal code: 12345', $response );
		$this->assertContains( 'City: Einort', $response );
		$this->assertContains( 'Country code: DE', $response );
		$this->assertContains( 'Email address: karla@kennichnich.de', $response );
	}

	public function testGivenInvalidRequest_formStillContainsBannerTrackingData(): void {
		$client = $this->createClient();
		$client->request(
			'POST',
			'/donation/add',
			[
				'impCount' => 12,
				'bImpCount' => 3
			]
		);

		$response = $client->getResponse()->getContent();

		$this->assertContains( 'Impression Count: 12', $response );
		$this->assertContains( 'Banner Impression Count: 3', $response );
	}

	public function testGivenNegativeDonationAmount_formIsReloadedAndPrefilledWithZero(): void {
		$client = $this->createClient();

		$formValues = $this->newInvalidFormInput();
		$formValues['betrag'] = '-5,00';

		$client->request(
			'POST',
			'/donation/add',
			$formValues
		);

		$response = $client->getResponse()->getContent();

		$this->assertContains( 'Amount: 0,00', $response );
	}

	private function newInvalidFormInput(): array {
		return [
			'betrag' => '0',
			'zahlweise' => 'BEZ',
			'periode' => 3,
			'iban' => 'DE12500105170648489890',
			'bic' => 'INGDDEFFXXX',
			'konto' => '0648489890',
			'blz' => '50010517',
			'bankname' => 'ING-DiBa',
			'addressType' => 'person',
			'salutation' => 'Frau',
			'title' => 'Prof. Dr.',
			'company' => '',
			'firstName' => 'Karla',
			'lastName' => 'Kennichnich',
			'street' => 'Lehmgasse 12',
			'postcode' => '12345',
			'city' => 'Einort',
			'country' => 'DE',
			'email' => 'karla@kennichnich.de',
			'info' => '1',
			'piwik_campaign' => 'test',
			'piwik_kwd' => 'gelb',
			'impCount' => '3',
			'bImpCount' => '1',
			'layout' => 'Default',
			'color' => 'blue',
			'skin' => 'default',
		];
	}

	public function testGivenInvalidAnonymousRequest_formIsReloadedAndPrefilled(): void {
		$client = $this->createClient();
		$client->request(
			'POST',
			'/donation/add',
			$this->newAnonymousFormInput()
		);

		$response = $client->getResponse()->getContent();

		$this->assertContains( 'Amount: 0', $response );
		$this->assertContains( 'Payment type: UEB', $response );
		$this->assertContains( 'Interval: 1', $response );
		$this->assertContains( 'Value of field "amount" violates rule: Amount too low', $response );
	}

	private function newAnonymousFormInput(): array {
		return [
			'betrag' => '0',
			'zahlweise' => 'UEB',
			'periode' => 1,
			'addressType' => 'anonym'
		];
	}

	public function testGivenValidRequest_tokensAreReturned(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {
			$factory->setTokenGenerator( new FixedTokenGenerator( self::SOME_TOKEN ) );

			$client->setServerParameter( 'HTTP_REFERER', 'https://en.wikipedia.org/wiki/Karla_Kennichnich' );
			$client->followRedirects( false );

			$client->request(
				'POST',
				'/donation/add',
				$this->newValidCreditCardInput()
			);

			$response = $client->getResponse()->getContent();

			$this->assertContains( self::SOME_TOKEN, $response );
		} );
	}

	public function testGivenValidRequest_clientIsRedirected(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {
			$factory->setTokenGenerator( new FixedTokenGenerator( self::SOME_TOKEN ) );
			$client->followRedirects( false );

			$client->request(
				'POST',
				'/donation/add',
				$this->newValidFormInput()
			);

			$this->assertTrue( $client->getResponse()->isRedirect() );
		} );
	}

	public function testWhenTrackingCookieExists_valueIsPersisted(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {
			$client->getCookieJar()->set( new Cookie( 'spenden_tracking', 'test/blue' ) );

			$client->request(
				'POST',
				'/donation/add',
				$this->newComplementableFormInput()
			);

			$donation = $this->getDonationFromDatabase( $factory );
			$data = $donation->getDecodedData();

			$this->assertSame( 'test/blue', $data['tracking'] );
		} );
	}

	public function testWhenTrackableInputDataIsSubmitted_theyAreStoredInSession(): void {
		$this->createAppEnvironment( [], function ( Client $client, FunFunFactory $factory, Application $app ) {

			$client->request(
				'GET',
				'/',
				[
					'betrag' => '5,00',
					'periode' => 3,
					'zahlweise' => 'BEZ'
				]
			);

			$piwikTracking = $app['session']->get( 'piwikTracking' );
			$this->assertSame( 'BEZ', $piwikTracking['paymentType'] );
			$this->assertSame( 3, $piwikTracking['paymentInterval'] );
			$this->assertSame( '5,00', $piwikTracking['paymentAmount'] );
		} );
	}

	public function testWhenTolstojNovelIsPassed_isIsNotStoredInSession(): void {
		$this->createAppEnvironment( [], function ( Client $client, FunFunFactory $factory, Application $app ) {

			$client->request(
				'GET',
				'/',
				[
					'betrag' => '5,00',
					'periode' => 3,
					'zahlweise' => 'Eh bien, mon prince. Gênes et Lucques ne sont plus que des apanages, des поместья, de la ' .
						'famille Buonaparte. Non, je vous préviens que si vous ne me dites pas que nous avons la guerre, si ' .
						'vous vous permettez encore de pallier toutes les infamies, toutes les atrocités de cet Antichrist ' .
						'(ma parole, j’y crois) — je ne vous connais plus, vous n’êtes plus mon ami, vous n’êtes plus мой ' .
						'верный раб, comme vous dites. Ну, здравствуйте,' .
						'здравствуйте. Je vois que je vous fais peur, ' .
						'садитесь и рассказывайте.'
				]
			);

			$piwikTracking = $app['session']->get( 'piwikTracking' );
			$this->assertArrayNotHasKey( 'paymentType', $piwikTracking );
			$this->assertSame( 3, $piwikTracking['paymentInterval'] );
			$this->assertSame( '5,00', $piwikTracking['paymentAmount'] );
		} );
	}

	public function testWhenParameterIsOmitted_itIsNotStoredInSession(): void {
		$this->createAppEnvironment( [], function ( Client $client, FunFunFactory $factory, Application $app ) {

			$client->request(
				'GET',
				'/',
				[
					'betrag' => '5,00',
					'zahlweise' => 'BEZ'
				]
			);

			$piwikTracking = $app['session']->get( 'piwikTracking' );
			$this->assertSame( 'BEZ', $piwikTracking['paymentType'] );
			$this->assertSame( '5,00', $piwikTracking['paymentAmount'] );
			$this->assertArrayNotHasKey( 'paymentInterval', $piwikTracking );
		} );
	}

	public function testWhenInitiallyIntendedPaymentOptionsDifferFromActual_itIsReflectedInPiwikTrackingEvents(): void {
		$client = $this->createClient( [] );
		$client->request(
			'GET',
			'/',
			[
				'betrag' => '5.00',
				'zahlweise' => 'BEZ',
				'periode' => 12
			]
		);

		$client->request(
			'POST',
			'/donation/add',
			[
				'addressType' => 'anonym',
				'betrag' => '12,34',
				'periode' => '0',
				'zahlweise' => 'UEB'
			]
		);
		$client->followRedirect();

		$responseContent = $client->getResponse()->getContent();
		$this->assertContains( 'BEZ/UEB', $responseContent );
		$this->assertContains( '5.00/12.34', $responseContent );
		$this->assertContains( '12/0', $responseContent );
	}

	public function testWhenMobileTrackingIsRequested_piwikTrackerIsCalledForPaypalPayment(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {
			$factory->setNullMessenger();
			$client->followRedirects( false );

			$tracker = $this->getMockBuilder( PageViewTracker::class )->disableOriginalConstructor()->getMock();
			$tracker->expects( $this->once() )
				->method( 'trackPaypalRedirection' )
				->with( 'test', 'gelb', '10.1.2.3' );
			$factory->setPageViewTracker( $tracker );

			$client->request(
				'POST',
				'/donation/add',
				$this->newValidMobilePayPalInput(),
				[],
				['REMOTE_ADDR' => '10.1.2.3']
			);

			$client->getResponse();
		} );
	}

	private function newValidMobilePayPalInput(): array {
		return [
			'betrag' => '12,34',
			'zahlweise' => 'PPL',
			'periode' => 3,
			'addressType' => 'anonym',
			'piwik_campaign' => 'test',
			'piwik_kwd' => 'gelb',
			'mbt' => '1' // mobile tracking param
		];
	}

	public function testWhenMobileTrackingIsRequested_piwikTrackerIsNotCalledForNonPaypalPayment(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {
			$factory->setNullMessenger();
			$client->followRedirects( false );

			$tracker = $this->getMockBuilder( PageViewTracker::class )->disableOriginalConstructor()->getMock();
			$tracker->expects( $this->never() )
				->method( 'trackPaypalRedirection' );
			$factory->setPageViewTracker( $tracker );

			$client->request(
				'POST',
				'/donation/add',
				array_merge(
					$this->newValidCreditCardInput(),
					['mbt' => '1']
				)
			);

			$client->getResponse();
		} );
	}

	public function testGivenCommasInStreetInput_donationGetsPersisted(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {

			$client->setServerParameter( 'HTTP_REFERER', 'https://en.wikipedia.org/wiki/Karla_Kennichnich' );
			$client->followRedirects( false );

			$formInput = $this->newValidFormInput();
			$formInput['street'] = ',Lehmgasse, 12,';

			$client->request(
				'POST',
				'/donation/add',
				$formInput
			);

			$this->assertIsExpectedDonation( $this->getDonationFromDatabase( $factory ) );
		} );
	}

	public function testGivenSufficientForeignBankData_donationGetsPersisted(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ) {
			$formInput = $this->newValidFormInput();
			$formInput['iban'] = 'AT022050302101023600';
			$formInput['bic'] = 'SPIHAT22XXX';
			$formInput['konto'] = '';
			$formInput['blz'] = '';
			$formInput['bankname'] = '';
			$client->request(
				'POST',
				'/donation/add',
				$formInput
			);

			$donation = $this->getDonationFromDatabase( $factory );
			$data = $donation->getDecodedData();

			$this->assertSame( 'AT022050302101023600', $data['iban'] );
			$this->assertSame( 'SPIHAT22XXX', $data['bic'] );
			$this->assertSame( '', $data['konto'] );
			$this->assertSame( '', $data['blz'] );
			$this->assertSame( '', $data['bankname'] );
		} );
	}
}
