<?php

namespace Hyperwallet;

use Hyperwallet\Exception\HyperwalletApiException;
use Hyperwallet\Exception\HyperwalletArgumentException;
use Hyperwallet\Model\AuthenticationToken;
use Hyperwallet\Model\Balance;
use Hyperwallet\Model\BankAccount;
use Hyperwallet\Model\BankAccountStatusTransition;
use Hyperwallet\Model\BankCard;
use Hyperwallet\Model\BankCardStatusTransition;
use Hyperwallet\Model\BusinessStakeholder;
use Hyperwallet\Model\BusinessStakeholderStatusTransition;
use Hyperwallet\Model\HyperwalletVerificationDocument;
use Hyperwallet\Model\HyperwalletVerificationDocumentReason;
use Hyperwallet\Model\HyperwalletVerificationDocumentCollection;
use Hyperwallet\Model\HyperwalletVerificationDocumentReasonCollection;
use Hyperwallet\Model\IProgramAware;
use Hyperwallet\Model\PaperCheck;
use Hyperwallet\Model\PaperCheckStatusTransition;
use Hyperwallet\Model\Payment;
use Hyperwallet\Model\PaymentStatusTransition;
use Hyperwallet\Model\PayPalAccount;
use Hyperwallet\Model\PayPalAccountStatusTransition;
use Hyperwallet\Model\PrepaidCard;
use Hyperwallet\Model\PrepaidCardStatusTransition;
use Hyperwallet\Model\Program;
use Hyperwallet\Model\ProgramAccount;
use Hyperwallet\Model\Receipt;
use Hyperwallet\Model\StatusTransition;
use Hyperwallet\Model\Transfer;
use Hyperwallet\Model\TransferMethod;
use Hyperwallet\Model\TransferMethodConfiguration;
use Hyperwallet\Model\TransferRefund;
use Hyperwallet\Model\TransferStatusTransition;
use Hyperwallet\Model\User;
use Hyperwallet\Model\UserStatusTransition;
use Hyperwallet\Model\VenmoAccount;
use Hyperwallet\Model\VenmoAccountStatusTransition;
use Hyperwallet\Model\WebhookNotification;
use Hyperwallet\Response\ListResponse;
use Hyperwallet\Util\ApiClient;

/**
 * The Hyperwallet SDK Client
 *
 * @package Hyperwallet
 */
class Hyperwallet {

    /**
     * The program token
     *
     * @var string
     */
    private $programToken;

    /**
     * The internal API client
     *
     * @var ApiClient
     */
    private $client;

    /**
     * The REST API Version
     *
     * @var string
     */
    private $version = 'v4';

    /**
     * Creates a instance of the SDK Client
     *
     * @param string $username The API username
     * @param string $password The API password
     * @param string|null $programToken The program token that is used for some API calls
     * @param string $server The API server to connect to
     * @param array $encryptionData Encryption data to initialize ApiClient with encryption enabled
     * @param array $clientOptions Guzzle Client Options
     *
     * @throws HyperwalletArgumentException
     */
    public function __construct($username, $password, $programToken = null, $server = 'https://api.sandbox.hyperwallet.com', $encryptionData = array(), $clientOptions = array(), $version = 'v4') {
        if (empty($username) || empty($password)) {
            throw new HyperwalletArgumentException('You need to specify your API username and password!');
        }

        $this->programToken = $programToken;
        $this->client = new ApiClient($username, $password, $server, $clientOptions, $encryptionData);
        $this->version = $version;
    }

    //--------------------------------------
    // Throttling
    //--------------------------------------
    public function getThrottling()
    {
        return $this->client->getThrottling();
    }

    //--------------------------------------
    // Helpers
    //--------------------------------------

    /**
     * Modify body for nested formatting
     *
     * @param array $bodyResponse Body Response from request
     * @return array
     */
    private function setDocumentAndReasonFromResponseHelper($bodyResponse) {
        if (array_key_exists("documents", $bodyResponse)) {
            $documents = $bodyResponse["documents"];
            foreach ($documents as &$dVal) {
                if (array_key_exists("reasons", $dVal)) {
                    $reasons = $dVal["reasons"];
                    foreach ($reasons as &$rVal) {
                        $rVal = new HyperwalletVerificationDocumentReason($rVal);
                    }
                    $dVal["reasons"] = new HyperwalletVerificationDocumentReasonCollection(...$reasons);
                }
                $dVal = new HyperwalletVerificationDocument($dVal);
            }
            $bodyResponse["documents"] = new HyperwalletVerificationDocumentCollection(...$documents);
        }
        return $bodyResponse;
    }


    //--------------------------------------
    // Users
    //--------------------------------------

    /**
     * Create a user
     *
     * @param User $user The user data
     * @return User
     *
     * @throws HyperwalletApiException
     */
    public function createUser(User $user) {
        $this->addProgramToken($user);
        $body = $this->client->doPost('/rest/{version}/users', array(
            'version' => $this->version
        ), $user, array());
        return new User($body);
    }

    /**
     * Get a user
     *
     * @param string $userToken The user token
     * @return User
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getUser($userToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/users/{user-token}', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), array());
        return new User($body);
    }

    /**
     * Update a user
     *
     * @param User $user The user
     * @return User
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function updateUser(User $user) {
        if (!$user->getToken()) {
            throw new HyperwalletArgumentException('token is required!');
        }
        $body = $this->client->doPut('/rest/{version}/users/{user-token}', array(
            'version' => $this->version,
            'user-token' => $user->getToken()
        ), $user, array());
        return new User($body);
    }

    /**
     * List all users
     *
     * @param array $options
     * @return ListResponse
     *
     * @throws HyperwalletApiException
     */
    public function listUsers($options = array()) {
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(User::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users', array(
            'version' => $this->version
        ),$options) ;
        return new ListResponse($body, function ($entry) {
            return new User($entry);
        });
    }

    /**
     * Get a user status transition
     *
     * @param string $userToken The user token
     * @param string $statusTransitionToken The status transition token
     * @return UserStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getUserStatusTransition($userToken, $statusTransitionToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new UserStatusTransition($body);
    }

    /**
     * List all user status transitions
     *
     * @param string $userToken The user token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listUserStatusTransitions($userToken, array $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key( $options, array_flip(StatusTransition::FILTERS_ARRAY()) );
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new UserStatusTransition($entry);
        });
    }

    //--------------------------------------
    // Authentication Token
    //--------------------------------------

    /**
     * Get authentication token
     *
     * @param string $userToken The user token
     * @return AuthenticationToken
     *
     * @throws HyperwalletApiException
     */
    public function getAuthenticationToken($userToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/authentication-token', array(
            'version' => $this->version,
            'user-token' => $userToken,
        ), null, array());
        return new AuthenticationToken($body);
    }

    //--------------------------------------
    // Paper Checks
    //--------------------------------------

    /**
     * Create a paper check
     *
     * @param string $userToken The user token
     * @param PaperCheck $paperCheck The paper check data
     * @return PaperCheck
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createPaperCheck($userToken, PaperCheck $paperCheck) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/paper-checks', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $paperCheck, array());
        return new PaperCheck($body);
    }

    /**
     * Get a paper check
     *
     * @param string $userToken The user token
     * @param string $paperCheckToken The paper check token
     * @return PaperCheck
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getPaperCheck($userToken, $paperCheckToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($paperCheckToken)) {
            throw new HyperwalletArgumentException('paperCheckToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/users/{user-token}/paper-checks/{paper-check-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'paper-check-token' => $paperCheckToken
        ), array());
        return new PaperCheck($body);
    }

    /**
     * Update a paper check
     *
     * @param string $userToken The user token
     * @param PaperCheck $paperCheck The paper check data
     * @return PaperCheck
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function updatePaperCheck($userToken, PaperCheck $paperCheck) {
        $body = $this->updateTransferMethod($userToken, $paperCheck, 'paper-checks');
        return new PaperCheck($body);
    }

    /**
     * List all paper checks
     *
     * @param string $userToken The user token
     * @param array $options The query parameters to send
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listPaperChecks($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(PaperCheck::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/paper-checks', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new PaperCheck($entry);
        });
    }

    /**
     * Deactivate a paper check
     *
     * @param string $userToken The user token
     * @param string $paperCheckToken The paper check token
     * @return PaperCheckStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function deactivatePaperCheck($userToken, $paperCheckToken) {
        $transition = new PaperCheckStatusTransition();
        $transition->setTransition(PaperCheckStatusTransition::TRANSITION_DE_ACTIVATED);

        return $this->createPaperCheckStatusTransition($userToken, $paperCheckToken, $transition);
    }

    /**
     * Create a paper check status transition
     *
     * @param string $userToken The user token
     * @param string $paperCheckToken The paper check token
     * @param PaperCheckStatusTransition $transition The status transition
     * @return PaperCheckStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createPaperCheckStatusTransition($userToken, $paperCheckToken, PaperCheckStatusTransition $transition) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($paperCheckToken)) {
            throw new HyperwalletArgumentException('paperCheckToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/users/{user-token}/paper-checks/{paper-check-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'paper-check-token' => $paperCheckToken
        ), $transition, array());
        return new PaperCheckStatusTransition($body);
    }

    /**
     * Get a paper check status transition
     *
     * @param string $userToken The user token
     * @param string $paperCheckToken The paper check token
     * @param string $statusTransitionToken The status transition token
     * @return PaperCheckStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getPaperCheckStatusTransition($userToken, $paperCheckToken, $statusTransitionToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($paperCheckToken)) {
            throw new HyperwalletArgumentException('paperCheckToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/paper-checks/{paper-check-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'paper-check-token' => $paperCheckToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new PaperCheckStatusTransition($body);
    }

    /**
     * List all paper check status transitions
     *
     * @param string $userToken The user token
     * @param string $paperCheckToken The paper check token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listPaperCheckStatusTransitions($userToken, $paperCheckToken, array $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($paperCheckToken)) {
            throw new HyperwalletArgumentException('paperCheckToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(StatusTransition::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/paper-checks/{paper-check-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'paper-check-token' => $paperCheckToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new PaperCheckStatusTransition($entry);
        });
    }

    //--------------------------------------
    // Transfers
    //--------------------------------------

    /**
     * Create a transfer
     *
     * @param Transfer $transfer The transfer data
     * @return Transfer
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createTransfer(Transfer $transfer) {
        if (empty($transfer->getSourceToken())) {
            throw new HyperwalletArgumentException('sourceToken is required!');
        }
        if (empty($transfer->getDestinationToken())) {
            throw new HyperwalletArgumentException('destinationToken is required!');
        }
        if (empty($transfer->getClientTransferId())) {
            throw new HyperwalletArgumentException('clientTransferId is required!');
        }
        $body = $this->client->doPost('/rest/{version}/transfers', array(
            'version' => $this->version,
        ), $transfer, array());
        return new Transfer($body);
    }

    public function createTransferRefund($transferToken, $transferRefund) {
        if (empty($transferRefund)) {
            throw new HyperwalletArgumentException('transferRefund is required!');
        }
        if (empty($transferToken)) {
            throw new HyperwalletArgumentException('transferToken is required!');
        }
        if (empty($transferRefund->getClientRefundId())) {
            throw new HyperwalletArgumentException('clientRefundId is required!');
        }
        $body = $this->client->doPost('/rest/{version}/transfers/{transfer-token}/refunds', array(
            'version' => $this->version,
            'transfer-token' => $transferToken
        ), $transferRefund, array());
        return new TransferRefund($body);
    }

    /**
     * Get a transfer
     *
     * @param string $transferToken The transfer token
     * @return Transfer
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getTransfer($transferToken) {
        if (empty($transferToken)) {
            throw new HyperwalletArgumentException('transferToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/transfers/{transfer-token}', array(
            'version' => $this->version,
            'transfer-token' => $transferToken
        ), array());
        return new Transfer($body);
    }

    public function getTransferRefund($transferToken, $refundToken) {
        if (empty($transferToken)) {
            throw new HyperwalletArgumentException('transferToken is required!');
        }
        if (empty($refundToken)) {
            throw new HyperwalletArgumentException('refundToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/transfers/{transfer-token}/refunds/{refund-token}', array(
            'version' => $this->version,
            'transfer-token' => $transferToken,
            'refund-token' => $refundToken),
            array());
        return new TransferRefund($body);
    }

    /**
     * List all transfers
     *
     * @param array $options The query parameters to send
     * @return ListResponse
     *
     * @throws HyperwalletApiException
     */
    public function listTransfers($options = array()) {
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(Transfer::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/transfers', array(
            'version' => $this->version,
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new Transfer($entry);
        });
    }

    /**
     * List all transfers
     *
     * @param array $options The query parameters to send
     * @return ListResponse
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listTransferRefunds($transferToken, array $options = array()) {
        if (empty($transferToken)) {
            throw new HyperwalletArgumentException('transferToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/transfers/{transfer-token}/refunds', array(
            'version' => $this->version,
            'transfer-token' => $transferToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new TransferRefund($entry);
        });
    }

    /**
     * Create a transfer status transition
     *
     * @param string $transferToken The transfer token
     * @param TransferStatusTransition $transition The status transition
     * @return TransferStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createTransferStatusTransition($transferToken, TransferStatusTransition $transition) {
        if (empty($transferToken)) {
            throw new HyperwalletArgumentException('transferToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/transfers/{transfer-token}/status-transitions', array(
            'version' => $this->version,
            'transfer-token' => $transferToken
        ), $transition, array());
        return new TransferStatusTransition($body);
    }

    /**
     * Get a transfer status transition
     *
     * @param string $transferToken The transfer token
     * @param string $statusTransitionToken The status transition token
     * @return TransferStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getTransferStatusTransition($transferToken, $statusTransitionToken) {
        if (empty($transferToken)) {
            throw new HyperwalletArgumentException('transferToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/transfers/{transfer-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'transfer-token' => $transferToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new TransferStatusTransition($body);
    }

    /**
     * List all transfer status transitions
     *
     * @param string $transferToken The transfer token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listTransferStatusTransitions($transferToken, array $options = array()) {
        if (empty($transferToken)) {
            throw new HyperwalletArgumentException('transfer token is required!');
        }

        $body = $this->client->doGet('/rest/{version}/transfers/{transfer-token}/status-transitions', array(
            'version' => $this->version,
            'transfer-token' => $transferToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new TransferStatusTransition($entry);
        });
    }

    //--------------------------------------
    // PayPal Accounts
    //--------------------------------------

    /**
     * Create a PayPal account
     *
     * @param string $userToken The user token
     * @param PayPalAccount $payPalAccount The PayPal account data
     * @return PayPalAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createPayPalAccount($userToken, PayPalAccount $payPalAccount) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($payPalAccount->getTransferMethodCountry())) {
            throw new HyperwalletArgumentException('transferMethodCountry is required!');
        }
        if (empty($payPalAccount->getTransferMethodCurrency())) {
            throw new HyperwalletArgumentException('transferMethodCurrency is required!');
        }
        if (empty($payPalAccount->getEmail()) and empty($payPalAccount->getAccountId()) ) {
            throw new HyperwalletArgumentException('email or accountId is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/paypal-accounts', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $payPalAccount, array());
        return new PayPalAccount($body);
    }

    /**
     * Get a PayPal account
     *
     * @param string $userToken The user token
     * @param string $payPalAccountToken The PayPal account token
     * @return PayPalAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getPayPalAccount($userToken, $payPalAccountToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($payPalAccountToken)) {
            throw new HyperwalletArgumentException('payPalAccountToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/users/{user-token}/paypal-accounts/{paypal-account-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'paypal-account-token' => $payPalAccountToken
        ), array());
        return new PayPalAccount($body);
    }

    /**
     * Update PayPal account
     *
     * @param string $userToken The user token
     * @param PayPalAccount $payPalAccount Paypal account data
     * @return PayPalAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function updatePayPalAccount($userToken, PayPalAccount $payPalAccount) {
        $body = $this->updateTransferMethod($userToken, $payPalAccount, 'paypal-accounts');
        return new PayPalAccount($body);
    }

    /**
     * List all PayPal accounts
     *
     * @param string $userToken The user token
     * @param array $options The query parameters to send
     * @return ListResponse
     *
     * @throws HyperwalletApiException
     */
    public function listPayPalAccounts($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(PayPalAccount::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/paypal-accounts', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new PayPalAccount($entry);
        });
    }

    /**
     * Deactivate a PayPal account
     *
     * @param string $userToken The user token
     * @param string $payPalAccountToken The PayPal account token
     * @return PayPalAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function deactivatePayPalAccount($userToken, $payPalAccountToken) {
        $transition = new PayPalAccountStatusTransition();
        $transition->setTransition(PayPalAccountStatusTransition::TRANSITION_DE_ACTIVATED);

        return $this->createPayPalAccountStatusTransition($userToken, $payPalAccountToken, $transition);
    }

    /**
     * Create a PayPal account status transition
     *
     * @param string $userToken The user token
     * @param string $payPalAccountToken The PayPal account token
     * @param PayPalAccountStatusTransition $transition The status transition
     * @return PayPalAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createPayPalAccountStatusTransition($userToken, $payPalAccountToken, PayPalAccountStatusTransition $transition) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($payPalAccountToken)) {
            throw new HyperwalletArgumentException('payPalAccountToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/users/{user-token}/paypal-accounts/{payPal-account-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'payPal-account-token' => $payPalAccountToken
        ), $transition, array());
        return new PayPalAccountStatusTransition($body);
    }

    /**
     * Get a PayPal account status transition
     *
     * @param string $userToken The user token
     * @param string $payPalAccountToken The PayPal account token
     * @param string $statusTransitionToken The status transition token
     * @return PayPalAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getPayPalAccountStatusTransition($userToken, $payPalAccountToken, $statusTransitionToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($payPalAccountToken)) {
            throw new HyperwalletArgumentException('payPalAccountToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/paypal-accounts/{payPal-account-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'payPal-account-token' => $payPalAccountToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new PayPalAccountStatusTransition($body);
    }

    /**
     * List all PayPal account status transitions
     *
     * @param string $userToken The user token
     * @param string $payPalAccountToken The payPal account token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listPayPalAccountStatusTransitions($userToken, $payPalAccountToken, array $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($payPalAccountToken)) {
            throw new HyperwalletArgumentException('payPalAccountToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(StatusTransition::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/paypal-accounts/{payPal-account-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'payPal-account-token' => $payPalAccountToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new PayPalAccountStatusTransition($entry);
        });
    }

    //--------------------------------------
    // Prepaid Cards
    //--------------------------------------

    /**
     * Create a prepaid card
     *
     * @param string $userToken The user token
     * @param PrepaidCard $prepaidCard The prepaid card data
     * @return PrepaidCard
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createPrepaidCard($userToken, PrepaidCard $prepaidCard) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/prepaid-cards', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $prepaidCard, array());
        return new PrepaidCard($body);
    }

    /**
     * Replace a prepaid card
     *
     * @param string $userToken The user token
     * @param PrepaidCard $prepaidCard The prepaid card data
     * @return PrepaidCard
     */
    public function replacePrepaidCard($userToken, PrepaidCard $prepaidCard) {
        if (empty($prepaidCard->getReplacementReason())) {
            throw new HyperwalletArgumentException('replacementReason is required!');
        }
        return $this->createPrepaidCard($userToken, $prepaidCard);
    }

    /**
     * Get a prepaid card
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @return PrepaidCard
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getPrepaidCard($userToken, $prepaidCardToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($prepaidCardToken)) {
            throw new HyperwalletArgumentException('prepaidCardToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/users/{user-token}/prepaid-cards/{prepaid-card-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'prepaid-card-token' => $prepaidCardToken
        ), array());
        return new PrepaidCard($body);
    }

    /**
     * Update a prepaid card
     *
     * @param string $userToken The user token
     * @param PrepaidCard $prepaidCard The prepaid card data
     * @return PrepaidCard
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function updatePrepaidCard($userToken, PrepaidCard $prepaidCard) {
        $body = $this->updateTransferMethod($userToken, $prepaidCard, 'prepaid-cards');
        return new PrepaidCard($body);
    }

    /**
     * List all prepaid cards
     *
     * @param string $userToken The user token
     * @param array $options The query parameters to send
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listPrepaidCards($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(PrepaidCard::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/prepaid-cards', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new PrepaidCard($entry);
        });
    }

    /**
     * Suspend a prepaid card
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @return PrepaidCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function suspendPrepaidCard($userToken, $prepaidCardToken) {
        $transition = new PrepaidCardStatusTransition();
        $transition->setTransition(PrepaidCardStatusTransition::TRANSITION_SUSPENDED);

        return $this->createPrepaidCardStatusTransition($userToken, $prepaidCardToken, $transition);
    }

    /**
     * Unsuspend a prepaid card
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @return PrepaidCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function unsuspendPrepaidCard($userToken, $prepaidCardToken) {
        $transition = new PrepaidCardStatusTransition();
        $transition->setTransition(PrepaidCardStatusTransition::TRANSITION_UNSUSPENDED);

        return $this->createPrepaidCardStatusTransition($userToken, $prepaidCardToken, $transition);
    }

    /**
     * Mark a prepaid card as lost or stolen
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @return PrepaidCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function lostOrStolenPrepaidCard($userToken, $prepaidCardToken) {
        $transition = new PrepaidCardStatusTransition();
        $transition->setTransition(PrepaidCardStatusTransition::TRANSITION_LOST_OR_STOLEN);

        return $this->createPrepaidCardStatusTransition($userToken, $prepaidCardToken, $transition);
    }

    /**
     * Deactivate a prepaid card
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @return PrepaidCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function deactivatePrepaidCard($userToken, $prepaidCardToken) {
        $transition = new PrepaidCardStatusTransition();
        $transition->setTransition(PrepaidCardStatusTransition::TRANSITION_DE_ACTIVATED);

        return $this->createPrepaidCardStatusTransition($userToken, $prepaidCardToken, $transition);
    }

    /**
     * Lock a prepaid card
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @return PrepaidCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function lockPrepaidCard($userToken, $prepaidCardToken) {
        $transition = new PrepaidCardStatusTransition();
        $transition->setTransition(PrepaidCardStatusTransition::TRANSITION_LOCKED);

        return $this->createPrepaidCardStatusTransition($userToken, $prepaidCardToken, $transition);
    }

    /**
     * Unlock a prepaid card
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @return PrepaidCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function unlockPrepaidCard($userToken, $prepaidCardToken) {
        $transition = new PrepaidCardStatusTransition();
        $transition->setTransition(PrepaidCardStatusTransition::TRANSITION_UNLOCKED);

        return $this->createPrepaidCardStatusTransition($userToken, $prepaidCardToken, $transition);
    }

    /**
     * Create a prepaid card status transition
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @param PrepaidCardStatusTransition $transition The status transition
     * @return PrepaidCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createPrepaidCardStatusTransition($userToken, $prepaidCardToken, PrepaidCardStatusTransition $transition) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($prepaidCardToken)) {
            throw new HyperwalletArgumentException('prepaidCardToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/users/{user-token}/prepaid-cards/{prepaid-card-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'prepaid-card-token' => $prepaidCardToken
        ), $transition, array());
        return new PrepaidCardStatusTransition($body);
    }

    /**
     * Get a prepaid card status transition
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @param string $statusTransitionToken The status transition token
     * @return PrepaidCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getPrepaidCardStatusTransition($userToken, $prepaidCardToken, $statusTransitionToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($prepaidCardToken)) {
            throw new HyperwalletArgumentException('prepaidCardToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/prepaid-cards/{prepaid-card-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'prepaid-card-token' => $prepaidCardToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new PrepaidCardStatusTransition($body);
    }

    /**
     * List all prepaid card status transitions
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listPrepaidCardStatusTransitions($userToken, $prepaidCardToken, array $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($prepaidCardToken)) {
            throw new HyperwalletArgumentException('prepaidCardToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(StatusTransition::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/prepaid-cards/{prepaid-card-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'prepaid-card-token' => $prepaidCardToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new PrepaidCardStatusTransition($entry);
        });
    }

    //--------------------------------------
    // Bank Accounts
    //--------------------------------------

    /**
     * Create a bank account
     *
     * @param string $userToken The user token
     * @param BankAccount $bankAccount The bank account data
     * @return BankAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createBankAccount($userToken, BankAccount $bankAccount) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/bank-accounts', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $bankAccount, array());
        return new BankAccount($body);
    }

    /**
     * Get a bank account
     *
     * @param string $userToken The user token
     * @param string $bankAccountToken The bank account token
     * @return BankAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getBankAccount($userToken, $bankAccountToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($bankAccountToken)) {
            throw new HyperwalletArgumentException('bankAccountToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/users/{user-token}/bank-accounts/{bank-account-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'bank-account-token' => $bankAccountToken
        ), array());
        return new BankAccount($body);
    }

    /**
     * Update a bank account
     *
     * @param string $userToken The user token
     * @param BankAccount $bankAccount The bank account data
     * @return BankAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function updateBankAccount($userToken, BankAccount $bankAccount) {
        $body = $this->updateTransferMethod($userToken, $bankAccount, 'bank-accounts');
        return new BankAccount($body);
    }

    /**
     * List all bank accounts
     *
     * @param string $userToken The user token
     * @param array $options The query parameters to send
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listBankAccounts($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(BankAccount::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/bank-accounts', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new BankAccount($entry);
        });
    }

    /**
     * Deactivate a bank account
     *
     * @param string $userToken The user token
     * @param string $bankAccountToken The bank account token
     * @return BankAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function deactivateBankAccount($userToken, $bankAccountToken) {
        $transition = new BankAccountStatusTransition();
        $transition->setTransition(BankAccountStatusTransition::TRANSITION_DE_ACTIVATED);

        return $this->createBankAccountStatusTransition($userToken, $bankAccountToken, $transition);
    }

    /**
     * Create a bank account status transition
     *
     * @param string $userToken The user token
     * @param string $bankAccountToken The bank account token
     * @param BankAccountStatusTransition $transition The status transition
     * @return BankAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createBankAccountStatusTransition($userToken, $bankAccountToken, BankAccountStatusTransition $transition) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($bankAccountToken)) {
            throw new HyperwalletArgumentException('bankAccountToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/users/{user-token}/bank-accounts/{bank-account-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'bank-account-token' => $bankAccountToken
        ), $transition, array());
        return new BankAccountStatusTransition($body);
    }

    /**
     * Get a bank account status transition
     *
     * @param string $userToken The user token
     * @param string $bankAccountToken The bank account token
     * @param string $statusTransitionToken The status transition token
     * @return BankAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getBankAccountStatusTransition($userToken, $bankAccountToken, $statusTransitionToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($bankAccountToken)) {
            throw new HyperwalletArgumentException('bankAccountToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/bank-accounts/{bank-account-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'bank-account-token' => $bankAccountToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new BankAccountStatusTransition($body);
    }

    /**
     * List all bank account status transitions
     *
     * @param string $userToken The user token
     * @param string $bankAccountToken The bank account token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listBankAccountStatusTransitions($userToken, $bankAccountToken, array $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($bankAccountToken)) {
            throw new HyperwalletArgumentException('bankAccountToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(StatusTransition::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/bank-accounts/{bank-account-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'bank-account-token' => $bankAccountToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new BankAccountStatusTransition($entry);
        });
    }

    //--------------------------------------
    // Bank Cards
    //--------------------------------------

    /**
     * Create Bank Card
     *
     * @param string $userToken The user token
     * @param BankCard $bankCard The bank card data
     * @return BankCard
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createBankCard($userToken, BankCard $bankCard) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/bank-cards', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $bankCard, array());
        return new BankCard($body);
    }

    /**
     * Get a bank card
     *
     * @param string $userToken The user token
     * @param string $bankCardToken The bank card token
     * @return BankCard
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getBankCard($userToken, $bankCardToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($bankCardToken)) {
            throw new HyperwalletArgumentException('bankCardToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/users/{user-token}/bank-cards/{bank-card-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'bank-card-token' => $bankCardToken
        ), array());
        return new BankCard($body);
    }


    /**
     * Update a bank card
     *
     * @param string $userToken The user token
     * @param BankCard $bankCard The bank card data
     * @return BankCard
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function updateBankCard($userToken, BankCard $bankCard) {
        $body = $this->updateTransferMethod($userToken, $bankCard, 'bank-cards');
        return new BankCard($body);
    }

    /**
     * List all bank cards
     *
     * @param string $userToken The user token
     * @param array $options The query parameters to send
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listBankCards($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(BankCard::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/bank-cards', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new BankCard($entry);
        });
    }

    /**
     * @param string $userToken The user token
     * @param string $bankCardToken The bank card token
     * @return BankCardStatusTransition
     *
     * @throws HyperwalletApiException
     * @throws HyperwalletArgumentException
     */
    public function deactivateBankCard($userToken, $bankCardToken) {
        $transition = new BankCardStatusTransition();
        $transition->setTransition(BankCardStatusTransition::TRANSITION_DE_ACTIVATED);

        return $this->createBankCardStatusTransition($userToken, $bankCardToken, $transition);
    }

    /**
     * Create a bank card status transition
     *
     * @param string $userToken The user token
     * @param string $bankCardToken The bank card token
     * @param BankCardStatusTransition $transition The status transition
     * @return BankCardStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createBankCardStatusTransition($userToken, $bankCardToken, BankCardStatusTransition $transition) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($bankCardToken)) {
            throw new HyperwalletArgumentException('bankCardToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/users/{user-token}/bank-cards/{bank-card-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'bank-card-token' => $bankCardToken
        ), $transition, array());
        return new BankCardStatusTransition($body);
    }

    /**
     * Get a bank card status transition
     *
     * @param string $userToken The user token
     * @param string $bankCardToken The bank card token
     * @param string $statusTransitionToken The status transition token
     * @return BankCardStatusTransition
     *
     * @throws HyperwalletApiException
     * @throws HyperwalletArgumentException
     */
    public function getBankCardStatusTransition($userToken, $bankCardToken, $statusTransitionToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($bankCardToken)) {
            throw new HyperwalletArgumentException('bankCardToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/bank-cards/{bank-card-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'bank-card-token' => $bankCardToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new BankCardStatusTransition($body);
    }

    /**
     * List all bank card status transitions
     *
     * @param string $userToken The user token
     * @param string $bankCardToken The bank card token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listBankCardStatusTransitions($userToken, $bankCardToken, array $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($bankCardToken)) {
            throw new HyperwalletArgumentException('bankCardToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(StatusTransition::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/bank-cards/{bank-card-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'bank-card-token' => $bankCardToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new BankCardStatusTransition($entry);
        });
    }

    //--------------------------------------
    // Transfer Methods
    //--------------------------------------

    /**
     * Create a transfer method
     *
     * @param string $userToken The user token
     * @param string $jsonCacheToken The json cache token supplied by the widget
     * @param TransferMethod $transferMethod The transfer method data (to override certain fields)
     * @return BankAccount|PrepaidCard
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createTransferMethod($userToken, $jsonCacheToken, TransferMethod $transferMethod = null) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($jsonCacheToken)) {
            throw new HyperwalletArgumentException('jsonCacheToken is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/transfer-methods', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $transferMethod, array(), array(
            'Json-Cache-Token' => $jsonCacheToken
        ));
        if ($body['type'] === PrepaidCard::TYPE_PREPAID_CARD) {
            return new PrepaidCard($body);
        }
        return new BankAccount($body);
    }

    //--------------------------------------
    // Balances
    //--------------------------------------

    /**
     * List balances for a user
     *
     * @param string $userToken The user token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     */
    public function listBalancesForUser($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(Balance::FILTERS_ARRAY_USER()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/balances', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        $listResponse = new ListResponse($body, function ($entry) {
            return new Balance($entry);
        });
        call_user_func(array($listResponse, 'unsetLinksAttribute'));
        return $listResponse;
    }

    /**
     * List balances for a prepaid card
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     */
    public function listBalancesForPrepaidCard($userToken, $prepaidCardToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($prepaidCardToken)) {
            throw new HyperwalletArgumentException('prepaidCardToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(Balance::FILTERS_ARRAY_PREPAID_CARD()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/prepaid-cards/{prepaid-card-token}/balances', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'prepaid-card-token' => $prepaidCardToken
        ), $options);
        $listResponse = new ListResponse($body, function ($entry) {
            return new Balance($entry);
        });
        call_user_func(array($listResponse, 'unsetLinksAttribute'));
        return $listResponse;
    }

    /**
     * List balances for a program account
     *
     * @param string $programToken The program token
     * @param string $accountToken The account token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     */
    public function listBalancesForAccount($programToken, $accountToken, $options = array()) {
        if (empty($programToken)) {
            throw new HyperwalletArgumentException('programToken is required!');
        }
        if (empty($accountToken)) {
            throw new HyperwalletArgumentException('accountToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(Balance::FILTERS_ARRAY_ACCOUNT()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/programs/{program-token}/accounts/{account-token}/balances', array(
            'version' => $this->version,
            'program-token' => $programToken,
            'account-token' => $accountToken
        ), $options);
        $listResponse = new ListResponse($body, function ($entry) {
            return new Balance($entry);
        });
        call_user_func(array($listResponse, 'unsetLinksAttribute'));
        return $listResponse;
    }

    //--------------------------------------
    // Payments
    //--------------------------------------

    /**
     * Create a payment
     *
     * @param Payment $payment The payment
     * @return Payment
     *
     * @throws HyperwalletApiException
     */
    public function createPayment(Payment $payment) {
        $this->addProgramToken($payment);
        $body = $this->client->doPost('/rest/{version}/payments', array(
            'version' => $this->version
        ), $payment, array());
        return new Payment($body);
    }

    /**
     * Get a payment
     *
     * @param string $paymentToken The payment token
     * @return Payment
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getPayment($paymentToken) {
        if (empty($paymentToken)) {
            throw new HyperwalletArgumentException('paymentToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/payments/{payment-token}', array(
            'version' => $this->version,
            'payment-token' => $paymentToken
        ), array());
        return new Payment($body);
    }

    /**
     * List all payments
     *
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletApiException
     */
    public function listPayments($options = array()) {
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(Payment::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/payments', array(
            'version' => $this->version
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new Payment($entry);
        });
    }

    /**
     * Create a payment status transition
     *
     * @param string $paymentToken The payment token
     * @param PaymentStatusTransition $transition The status transition
     * @return PaymentStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createPaymentStatusTransition($paymentToken, PaymentStatusTransition $transition) {
        if (empty($paymentToken)) {
            throw new HyperwalletArgumentException('paymentToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/payments/{payment-token}/status-transitions', array(
            'version' => $this->version,
            'payment-token' => $paymentToken
        ), $transition, array());
        return new PaymentStatusTransition($body);
    }

    /**
     * Get a payment status transition
     *
     * @param string $paymentToken The payment token
     * @param string $statusTransitionToken The status transition token
     * @return PaymentStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getPaymentStatusTransition($paymentToken, $statusTransitionToken) {
        if (empty($paymentToken)) {
            throw new HyperwalletArgumentException('paymentToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/payments/{payment-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'payment-token' => $paymentToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new PaymentStatusTransition($body);
    }

    /**
     * List all payment status transitions
     *
     * @param string $paymentToken The payment token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listPaymentStatusTransitions($paymentToken, array $options = array()) {
        if (empty($paymentToken)) {
            throw new HyperwalletArgumentException('paymentToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(StatusTransition::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/payments/{payment-token}/status-transitions', array(
            'version' => $this->version,
            'payment-token' => $paymentToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new PaymentStatusTransition($entry);
        });
    }

    //--------------------------------------
    // Programs
    //--------------------------------------

    /**
     * Get a program
     *
     * @param string $programToken The program token
     * @return Program
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getProgram($programToken) {
        if (empty($programToken)) {
            throw new HyperwalletArgumentException('programToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/programs/{program-token}', array(
            'version' => $this->version,
            'program-token' => $programToken
        ), array());
        return new Program($body);
    }

    //--------------------------------------
    // Program Accounts
    //--------------------------------------

    /**
     * Get a program account
     *
     * @param string $programToken The program token
     * @param string $accountToken The account token
     * @return ProgramAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getProgramAccount($programToken, $accountToken) {
        if (empty($programToken)) {
            throw new HyperwalletArgumentException('programToken is required!');
        }
        if (empty($accountToken)) {
            throw new HyperwalletArgumentException('accountToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/programs/{program-token}/accounts/{account-token}', array(
            'version' => $this->version,
            'program-token' => $programToken,
            'account-token' => $accountToken
        ), array());
        return new ProgramAccount($body);
    }

    //--------------------------------------
    // Transfer Method Configurations
    //--------------------------------------

    /**
     * Get a transfer method configuration
     *
     * @param string $userToken The user token
     * @param string $country The transfer method country
     * @param string $currency The transfer method currency
     * @param string $type The transfer method type
     * @param string $profileType The profile type
     * @return TransferMethodConfiguration
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getTransferMethodConfiguration($userToken, $country, $currency, $type, $profileType) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($country)) {
            throw new HyperwalletArgumentException('country is required!');
        }
        if (empty($currency)) {
            throw new HyperwalletArgumentException('currency is required!');
        }
        if (empty($type)) {
            throw new HyperwalletArgumentException('type is required!');
        }
        if (empty($profileType)) {
            throw new HyperwalletArgumentException('profileType is required!');
        }

        $body = $this->client->doGet('/rest/{version}/transfer-method-configurations', array('version' => $this->version), array(
            'userToken' => $userToken,
            'country' => $country,
            'currency' => $currency,
            'type' => $type,
            'profileType' => $profileType
        ));
        return new TransferMethodConfiguration($body);
    }

    /**
     * List all transfer method configurations
     *
     * @param string $userToken The user token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listTransferMethodConfigurations($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(TransferMethodConfiguration::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/transfer-method-configurations', array(
            'version' => $this->version,
        ), array_merge(array(
            'userToken' => $userToken,
        ), $options));
        return new ListResponse($body, function ($entity) {
            return new TransferMethodConfiguration($entity);
        });
    }

    //--------------------------------------
    // Receipts
    //--------------------------------------

    /**
     * List all program account receipts
     *
     * @param string $programToken The program token
     * @param string $accountToken The program account token
     * @param array $options The query parameters
     * @return ListResponse of HyperwalletReceipt
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listReceiptsForProgramAccount($programToken, $accountToken, $options = array()) {
        if (empty($programToken)) {
            throw new HyperwalletArgumentException('programToken is required!');
        }
        if (empty($accountToken)) {
            throw new HyperwalletArgumentException('accountToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(Receipt::FILTERS_ARRAY_ACCOUNT()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/programs/{program-token}/accounts/{account-token}/receipts', array(
            'version' => $this->version,
            'program-token' => $programToken,
            'account-token' => $accountToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new Receipt($entry);
        });
    }

    /**
     * List all user receipts
     *
     * @param string $userToken The user token
     * @param array $options The query parameters
     * @return ListResponse of HyperwalletReceipt
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listReceiptsForUser($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(Receipt::FILTERS_ARRAY_USER()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/receipts', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new Receipt($entry);
        });
    }

    /**
     * List all prepaid card receipts
     *
     * @param string $userToken The user token
     * @param string $prepaidCardToken The prepaid card token
     * @param array $options The query parameters
     * @return ListResponse of HyperwalletReceipt
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listReceiptsForPrepaidCard($userToken, $prepaidCardToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($prepaidCardToken)) {
            throw new HyperwalletArgumentException('prepaidCardToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(Receipt::FILTERS_ARRAY_PREPAID_CARD()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/prepaid-cards/{prepaid-card-token}/receipts', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'prepaid-card-token' => $prepaidCardToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new Receipt($entry);
        });
    }

    //--------------------------------------
    // Webhook Notifications
    //--------------------------------------

    /**
     * Get a webhook notification
     *
     * @param string $webhookNotificationToken The webhook notification token
     * @return WebhookNotification
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getWebhookNotification($webhookNotificationToken) {
        if (empty($webhookNotificationToken)) {
            throw new HyperwalletArgumentException('webhookNotificationToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/webhook-notifications/{webhook-notification-token}', array(
            'version' => $this->version,
            'webhook-notification-token' => $webhookNotificationToken
        ), array());
        return new WebhookNotification($body);
    }

    /**
     * List all webhook notifications
     *
     * @param array $options
     * @return ListResponse
     *
     * @throws HyperwalletApiException
     */
    public function listWebhookNotifications($options = array()) {
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(WebhookNotification::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/webhook-notifications', array(
            'version' => $this->version
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new WebhookNotification($entry);
        });
    }

    //--------------------------------------
    // Internal utils
    //--------------------------------------

    /**
     * Add program token if global specified
     *
     * @param IProgramAware $model The model
     */
    private function addProgramToken(IProgramAware $model) {
        if (empty($this->programToken)) {
            return;
        }
        if ($model->getProgramToken()) {
            return;
        }
        $model->setProgramToken($this->programToken);
    }

    /**
     * Update Transfer method
     *
     * @param string $userToken The user token
     * @param object $transferMethod Transfer method data
     * @param string $transferMethodName Transfer method name to be used in url
     * @return object Updated transfer method object
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    private function updateTransferMethod($userToken, $transferMethod, $transferMethodName) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!$transferMethod->getToken()) {
            throw new HyperwalletArgumentException('transfer method token is required!');
        }

        return $this->client->doPut('/rest/{version}/users/{user-token}/{transfer-method-name}/{transfer-method-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'transfer-method-token' => $transferMethod->getToken(),
            'transfer-method-name' => $transferMethodName,
        ), $transferMethod, array());
    }

    /*
     * Update user verification status
     *
     * @param string $userToken The user token
     * @param UserStatusTransition $transition The status transition
     * @return UserStatusTransition
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function updateVerificationStatus($userToken, $verificationStatus){
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($verificationStatus)) {
            throw new HyperwalletArgumentException('verificationStatus is required!');
        }
        $user = new User(array('verificationStatus'=> $verificationStatus));
        $responseUser = $this->client->doPut('/rest/{version}/users/{user-token}', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $user, array());
        return new User($responseUser);
    }

    /**
     * Create an User status transition
     *
     * @param string $userToken The user token
     * @param UserStatusTransition $transition The status transition
     * @return UserStatusTransition
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
    */
    public function createUserStatusTransition($userToken, UserStatusTransition $transition) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($transition->getTransition())) {
            throw new HyperwalletArgumentException('userStatusTransition is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $transition, array());
        return new UserStatusTransition($body);
    }

    /**
     * Activate a User
     *
     * @param string $userToken The user token
     * @return UserStatusTransition the status transition
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function activateUser($userToken) {
        $transition = new UserStatusTransition();
        $transition->setTransition(UserStatusTransition::TRANSITION_ACTIVATED);
        return $this->createUserStatusTransition($userToken, $transition);
    }

    /**
     * De-activate a User
     *
     * @param string $userToken The user token
     * @return UserStatusTransition the status transition
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function deactivateUser($userToken) {
        $transition = new UserStatusTransition();
        $transition->setTransition(UserStatusTransition::TRANSITION_DE_ACTIVATED);
        return $this->createUserStatusTransition($userToken, $transition);
    }

    /**
     * Lock a User account
     *
     * @param string $userToken User token
     * @return UserStatusTransition the status transition
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function lockUser($userToken) {
        $transition = new UserStatusTransition();
        $transition->setTransition(UserStatusTransition::TRANSITION_LOCKED);
        return $this->createUserStatusTransition($userToken, $transition);
    }

    /**
     * Freeze a User account
     *
     * @param string $userToken User token
     * @return UserStatusTransition the status transition
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function freezeUser($userToken) {
        $transition = new UserStatusTransition();
        $transition->setTransition(UserStatusTransition::TRANSITION_FROZEN);
        return $this->createUserStatusTransition($userToken, $transition);
    }

    /**
     * Pre-activate a User account
     *
     * @param string $userToken User token
     * @return UserStatusTransition the status transition
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function preactivateUser($userToken) {
        $transition = new UserStatusTransition();
        $transition->setTransition(UserStatusTransition::TRANSITION_PRE_ACTIVATED);
        return $this->createUserStatusTransition($userToken, $transition);
    }


    /**
     * Upload documents for user endpoint
     *
     * @param string $userToken The user token
     * @param array $options The multipart object with the required documents and json data to get uploaded
     *
     * Sample multipart array to refer. Don't set the content-type explicitly
     * array(
     *'multipart' => [
     *  [
     *   'name'     => 'data',
     *  'contents' => '{"documents":[{"type":"DRIVERS_LICENSE","country":"US","category":"IDENTIFICATION"}]}'
     *  ],
     *  [
     *  'name'     => 'drivers_license_front',
     *  'contents' => fopen('<path>/File1.png', "r")
     *  ],
     *  [
     *  'name'     => 'drivers_license_back',
     *  'contents' => fopen('<path>>/File2.png', 'r')
     *  ]
     *  ]
     *  ));
     *
     * @return User user object with updated VerificationStatus and document details
     *
     */

    public function uploadDocumentsForUser($userToken, $options) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        $body = $this->client->putMultipartData('/rest/{version}/users/{user-token}', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        $body = $this->setDocumentAndReasonFromResponseHelper($body);
        return new User($body);
    }

    //--------------------------------------
    // Venmo Accounts
    //--------------------------------------

    /**
     * Create a Venmo account
     *
     * @param string $userToken user token
     * @param VenmoAccount $venmoAccount Venmo account data
     * @return VenmoAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createVenmoAccount($userToken, VenmoAccount $venmoAccount) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($venmoAccount->getTransferMethodCountry())) {
            throw new HyperwalletArgumentException('transferMethodCountry is required!');
        }
        if (empty($venmoAccount->getTransferMethodCurrency())) {
            throw new HyperwalletArgumentException('transferMethodCurrency is required!');
        }
        if (empty($venmoAccount->getAccountId())) {
            throw new HyperwalletArgumentException('Venmo account is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/venmo-accounts', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $venmoAccount, array());
        return new VenmoAccount($body);
    }

    /**
     * Get a Venmo account
     *
     * @param string $userToken user token
     * @param string $venmoAccountToken Venmo account token
     * @return VenmoAccount
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getVenmoAccount($userToken, $venmoAccountToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($venmoAccountToken)) {
            throw new HyperwalletArgumentException('venmoAccountToken is required!');
        }
        $body = $this->client->doGet('/rest/{version}/users/{user-token}/venmo-accounts/{venmo-account-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'venmo-account-token' => $venmoAccountToken
        ), array());
        return new VenmoAccount($body);
    }

    /**
     * Update Venmo account
     *
     * @param string $userToken user token
     * @param VenmoAccount $venmoAccount Venmo account data
     * @return VenmoAccount
     *
     */
    public function updateVenmoAccount($userToken, VenmoAccount $venmoAccount) {
        $body = $this->updateTransferMethod($userToken, $venmoAccount, 'venmo-accounts');
        return new VenmoAccount($body);
    }

    /**
     * List all Venmo accounts
     *
     * @param string $userToken user token
     * @param array $options The query parameters to send
     * @return ListResponse
     *
     * @throws HyperwalletApiException
     */
    public function listVenmoAccounts($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(VenmoAccount::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/venmo-accounts', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new VenmoAccount($entry);
        });
    }

    /**
     * Deactivate a Venmo account
     *
     * @param string $userToken user token
     * @param string $venmoAccountToken Venmo account token
     * @return VenmoAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function deactivateVenmoAccount($userToken, $venmoAccountToken) {
        $transition = new VenmoAccountStatusTransition();
        $transition->setTransition(VenmoAccountStatusTransition::TRANSITION_DE_ACTIVATED);

        return $this->createVenmoAccountStatusTransition($userToken, $venmoAccountToken, $transition);
    }

    /**
     * Create a Venmo account status transition
     *
     * @param string $userToken user token
     * @param string $venmoAccountToken Venmo account token
     * @param VenmoAccountStatusTransition $transition status transition
     * @return VenmoAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createVenmoAccountStatusTransition($userToken, $venmoAccountToken, VenmoAccountStatusTransition $transition) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($venmoAccountToken)) {
            throw new HyperwalletArgumentException('venmoAccountToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/users/{user-token}/venmo-accounts/{venmo-account-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'venmo-account-token' => $venmoAccountToken
        ), $transition, array());
        return new VenmoAccountStatusTransition($body);
    }

    /**
     * Get a Venmo account status transition
     *
     * @param string $userToken user token
     * @param string $venmoAccountToken Venmo account token
     * @param string $statusTransitionToken status transition token
     * @return VenmoAccountStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getVenmoAccountStatusTransition($userToken, $venmoAccountToken, $statusTransitionToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($venmoAccountToken)) {
            throw new HyperwalletArgumentException('venmoAccountToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/venmo-accounts/{venmo-account-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'venmo-account-token' => $venmoAccountToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new VenmoAccountStatusTransition($body);
    }

    /**
     * List all Venmo account status transitions
     *
     * @param string $userToken user token
     * @param string $venmoAccountToken Venmo account token
     * @param array $options query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listVenmoAccountStatusTransitions($userToken, $venmoAccountToken, array $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($venmoAccountToken)) {
            throw new HyperwalletArgumentException('venmoAccountToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(StatusTransition::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/venmo-accounts/{venmo-account-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'venmo-account-token' => $venmoAccountToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new VenmoAccountStatusTransition($entry);
        });
    }
    /**
     * Upload documents for Business Stakeholder endpoint
     *
     * @param string $userToken The user token
     * @param array $options The multipart object with the required documents and json data to get uploaded
     *
     * Sample multipart array to refer. Don't set the content-type explicitly
     * array(
     *'multipart' => [
     *  [
     *   'name'     => 'data',
     *  'contents' => '{"documents":[{"type":"DRIVERS_LICENSE","country":"US","category":"IDENTIFICATION"}]}'
     *  ],
     *  [
     *  'name'     => 'drivers_license_front',
     *  'contents' => fopen('<path>/File1.png', "r")
     *  ],
     *  [
     *  'name'     => 'drivers_license_back',
     *  'contents' => fopen('<path>>/File2.png', 'r')
     *  ]
     *  ]
     *  ));
     *
     * @return BusinessStakeholder object with updated VerificationStatus and document details
     *
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function uploadDocumentsForBusinessStakeholder($userToken, $businessToken, $options) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($businessToken)) {
            throw new HyperwalletArgumentException('businessToken is required!');
        }
        $body = $this->client->putMultipartData('/rest/{version}/users/{user-token}/business-stakeholders/{business-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'business-token' => $businessToken
        ), $options);
        $body = $this->setDocumentAndReasonFromResponseHelper($body);
        return new BusinessStakeholder($body);
    }

    /**
     * Create a Business Stakeholder
     *
     * @param string $userToken The user token
     * @param BusinessStakeholder $businessStakeholder The business stakeholder
     * @return BusinessStakeholder
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createBusinessStakeholder($userToken, $businessStakeholder) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        $body = $this->client->doPost('/rest/{version}/users/{user-token}/business-stakeholders', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $businessStakeholder, array());
        return new BusinessStakeholder($body);
    }

    /**
     * Update a Business Stakeholder
     *
     * @return BusinessStakeholder
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function updateBusinessStakeholder($userToken, $businessToken, $businessStakeholder) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($businessToken)) {
            throw new HyperwalletArgumentException('businessToken is required!');
        }
        $body = $this->client->doPut('/rest/{version}/users/{user-token}/business-stakeholders/{business-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'business-token' => $businessToken
        ), $businessStakeholder, array());
        return new BusinessStakeholder($body);
    }

    /**
     * List all Business Stakeholders
     *
     * @param array $options
     * @return ListResponse
     *
     * @throws HyperwalletApiException
     */

    public function listBusinessStakeholders($userToken , $options) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(BusinessStakeholder::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }
        $body = $this->client->doGet('/rest/{version}/users/{user-token}/business-stakeholders',array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new BusinessStakeholder($entry);
        });
    }

    /**
     * Create a Business Stakeholder status transition
     *
     * @param string $userToken The user token
     * @param string $businessToken The Business Token
     * @param BusinessStakeholderStatusTransition $transition The status transition
     * @return BusinessStakeholderStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function createBusinessStakeholderStatusTransition($userToken, $businessToken, BusinessStakeholderStatusTransition $transition) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($businessToken)) {
            throw new HyperwalletArgumentException('businessToken is required!');
        }

        $body = $this->client->doPost('/rest/{version}/users/{user-token}/business-stakeholders/{business-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'business-token' => $businessToken
        ), $transition, array());
        return new BusinessStakeholderStatusTransition($body);
    }

    /**
     * activate a Business Stakeholder
     *
     * @param string $userToken The user token
     * @param string $businessToken The Business Token
     * @return BusinessStakeholderStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function activateBusinessStakeholder($userToken, $businessToken) {
        $transition = new BusinessStakeholderStatusTransition();
        $transition->setTransition(BusinessStakeholderStatusTransition::TRANSITION_ACTIVATED);

        return $this->createBusinessStakeholderStatusTransition($userToken, $businessToken, $transition);
    }

    /**
     * Deactivate a Business Stakeholder
     *
     * @param string $userToken The user token
     * @param string $businessToken The Business Token
     * @return BusinessStakeholderStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function deactivateBusinessStakeholder($userToken, $businessToken) {
        $transition = new BusinessStakeholderStatusTransition();
        $transition->setTransition(BusinessStakeholderStatusTransition::TRANSITION_DE_ACTIVATED);

        return $this->createBusinessStakeholderStatusTransition($userToken, $businessToken, $transition);
    }

    /**
     * Get a Business Stakeholder status transition
     *
     * @param string $userToken The user token
     * @param string $businessToken The Business Token
     * @param string $statusTransitionToken The status transition token
     * @return BusinessStakeholderStatusTransition
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function getBusinessStakeholderStatusTransition($userToken, $businessToken, $statusTransitionToken) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($businessToken)) {
            throw new HyperwalletArgumentException('businessToken is required!');
        }
        if (empty($statusTransitionToken)) {
            throw new HyperwalletArgumentException('statusTransitionToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/business-stakeholders/{business-token}/status-transitions/{status-transition-token}', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'business-token' => $businessToken,
            'status-transition-token' => $statusTransitionToken
        ), array());
        return new BusinessStakeholderStatusTransition($body);
    }

    /**
     * List all Business Stakeholder status transitions
     *
     * @param string $userToken The user token
     * @param string $businessToken The Business Token
     * @param array $options The query parameters
     * @return ListResponse
     *
     * @throws HyperwalletArgumentException
     * @throws HyperwalletApiException
     */
    public function listBusinessStakeholderStatusTransitions($userToken, $businessToken, array $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }
        if (empty($businessToken)) {
            throw new HyperwalletArgumentException('businessToken is required!');
        }
        if (!empty($options)) {
            $filteredArr = array_diff_key($options, array_flip(StatusTransition::FILTERS_ARRAY()));
            if (!empty($filteredArr)) {
                throw new HyperwalletArgumentException('Invalid filter');
            }
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/business-stakeholders/{business-token}/status-transitions', array(
            'version' => $this->version,
            'user-token' => $userToken,
            'business-token' => $businessToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new BusinessStakeholderStatusTransition($entry);
        });
    }

    /**
     * List all Transfer Methods
     *
     * @param string $userToken The user token
     * @param array $options The query parameters
     * @return ListResponse of HyperwalletTransferMethod
     */

     public function listTransferMethods($userToken, $options = array()) {
        if (empty($userToken)) {
            throw new HyperwalletArgumentException('userToken is required!');
        }

        $body = $this->client->doGet('/rest/{version}/users/{user-token}/transfer-methods', array(
            'version' => $this->version,
            'user-token' => $userToken
        ), $options);
        return new ListResponse($body, function ($entry) {
            return new TransferMethod($entry);
        });
    }
}
