<?php

namespace Pauly4it\LaraTurk;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;

class MechanicalTurk
{
    private $MTURK_SERVICE = 'AWSMechanicalTurkRequester';
    private $endpoint;
    private $aws_access_key;
    private $aws_secret_key;
    private $guzzle;
    private $defaults;

    /**
     * Sets AWS keys, endpoint, and default config values, plus creates Guzzle client for API calls.
     *
     * @throws LaraTurkException if AWS keys are not set
     */
    public function __construct()
    {
        // check if AWS root account keys have been configured
        if (config('laraturk.credentials.AWS_ROOT_ACCESS_KEY_ID') === false or
             config('laraturk.credentials.AWS_ROOT_SECRET_ACCESS_KEY') === false) {
            throw new LaraTurkException('AWS Root account keys must be set as environment variables.');
        }

        // check if AWS region has been set
        if (config('laraturk.credentials.production.region') === false) {
            throw new LaraTurkException('AWS Region must be set in config file.');
        }

        $this->aws_access_key = config('laraturk.credentials.AWS_ROOT_ACCESS_KEY_ID');
        $this->aws_secret_key = config('laraturk.credentials.AWS_ROOT_SECRET_ACCESS_KEY');

        // set endpoint and config defaults to the Production site
        $region = config('laraturk.defaults.production.region');
        $this->endpoint = 'https://mturk-requester.'.$region.'.amazonaws.com';

        $this->defaults = config('laraturk.defaults.production');
        $this->guzzle = new Client();
    }

    /**
     * Sets the API in Sandbox Mode.
     * All API calls will go to the sandbox Amazon Mechanical Turk site and will use sandbox default config parameters.
     */
    public function setSandboxMode()
    {
        $region = config('laraturk.defaults.sandbox.region');
        $this->endpoint = 'https://mturk-requester-sandbox.'.$region.'.amazonaws.com';
        $this->defaults = array_merge(config('laraturk.defaults.production'), config('laraturk.defaults.sandbox'));
    }

    /**
     * Sets the API in Production Mode.
     * All API calls will go to the production Amazon Mechanical Turk site and will use production default config parameters.
     */
    public function setProductionMode()
    {
        $this->endpoint = 'https://mechanicalturk.amazonaws.com/';
        $this->defaults = config('laraturk.defaults.production');
    }

    /**
     * Creates a HIT based on an existing HITTypeID and HITLayoutID.
     * Reference: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_CreateHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function createHITByTypeIdAndByLayoutId($params = [])
    {
        // build params from defaults and passed values
        $params = array_merge($this->defaults, $params);

        // define required and optional parameters for this API request
        $required = ['HITTypeId', 'HITLayoutId', 'LifetimeInSeconds', 'MaxAssignments'];
        $optional = ['RequesterAnnotation', 'UniqueRequestToken'];
        // TODO: AssignmentReviewPolicy http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_AssignmentReviewPolicies.html
        // TODO: HITReviewPolicy http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_HITReviewPolicies.html

        // build the URL for the API call
        $url = $this->buildURL(
            'CreateHIT',
            $params,
            $required,
            $optional,
            [
                $this->generateHITLayoutParameters($params),
            ]
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'HIT');
    }

    /**
     * Creates a HIT based on an existing HITLayoutID.
     * Reference: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_CreateHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function createHITByLayoutId($params = [])
    {
        // build params from defaults and passed values
        $params = array_merge($this->defaults, $params);

        // define required and optional parameters for this API request
        $required = ['Title', 'Description', 'HITLayoutId', 'AssignmentDurationInSeconds', 'LifetimeInSeconds', 'MaxAssignments', 'AutoApprovalDelayInSeconds'];
        $optional = ['RequesterAnnotation', 'UniqueRequestToken'];
        // TODO: AssignmentReviewPolicy http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_AssignmentReviewPolicies.html
        // TODO: HITReviewPolicy http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_HITReviewPolicies.html

        // build the URL for the API call
        $url = $this->buildURL(
            'CreateHIT',
            $params,
            $required,
            $optional,
            [
                $this->generateReward($params),
                $this->generateHITLayoutParameters($params),
                $this->generateKeywords($params),
                $this->generateQualificationRequirement($params),
            ]
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'HIT');
    }

    /**
     * Creates a new HIT type.
     * Reference: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_RegisterHITTypeOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function registerHITType($params = [])
    {
        // build params from defaults and passed values
        $params = array_merge($this->defaults, $params);

        // define required and optional parameters for this API request
        $required = ['Title', 'Description', 'AssignmentDurationInSeconds', 'AutoApprovalDelayInSeconds'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'RegisterHITType',
            $params,
            $required,
            $optional,
            [
                $this->generateReward($params),
                $this->generateKeywords($params),
                $this->generateQualificationRequirement($params),
            ]
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'RegisterHITTypeResult');
    }

    /**
     * Creates, updates, disables, or re-enables notifications for the specified HIT Type Id.
     * Reference: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_SetHITTypeNotificationOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function setHITTypeNotification($params = [])
    {
        // build params from defaults and passed values
        $params = array_merge($this->defaults, $params);

        // define required and optional parameters for this API request
        $required = ['HITTypeId'];
        $optional = ['Active'];

        // build the URL for the API call
        $url = $this->buildURL(
            'SetHITTypeNotification',
            $params,
            $required,
            $optional,
            [
                $this->generateNotificationParameters($params),
            ]
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'SetHITTypeNotificationResult');
    }

    /**
     * Changes the HITType property of a HIT.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_ChangeHITTypeOfHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function changeHITTypeOfHIT($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['HITId', 'HITTypeId'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'ChangeHITTypeOfHIT',
            $params,
            $required
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'ChangeHITTypeOfHITResult');
    }

    /**
     * Retrieves the details of a HIT.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getHIT($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['HITId'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetHIT',
            $params,
            $required
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'HIT');
    }

    /**
     * Returns all of a Requester's HITs.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_SearchHITsOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function searchHITs($params = [])
    {
        // define required and optional parameters for this API request
        $required = [];
        $optional = ['SortProperty', 'SortDirection', 'PageSize', 'PageNumber'];

        // build the URL for the API call
        $url = $this->buildURL(
            'SearchHITs',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'SearchHITsResult');
    }

    /**
     * Retrieves all HITS in a 'Reviewable' or 'Reviewing' status.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetReviewableHITsOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getReviewableHITs($params = [])
    {
        // define required and optional parameters for this API request
        $required = [];
        $optional = ['HITTypeId', 'Status', 'SortProperty', 'SortDirection', 'PageSize', 'PageNumber'];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetReviewableHITs',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetReviewableHITsResult');
    }

    /**
     * Retrieves the completed assignments for the HIT.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetAssignmentsForHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getAssignmentsForHIT($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['HITId'];
        $optional = ['AssignmentStatus', 'SortProperty', 'SortDirection', 'PageSize', 'PageNumber'];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetAssignmentsForHIT',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetAssignmentsForHITResult');
    }

    /**
     * Retrieves a submitted, approved, or rejected assignment by AssignmentId.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetAssignmentOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getAssignment($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['AssignmentId'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetAssignment',
            $params,
            $required
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetAssignmentResult');
    }

    /**
     * Approves the results of a completed assignment.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_ApproveAssignmentOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function approveAssignment($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['AssignmentId'];
        $optional = ['RequesterFeedback'];

        // build the URL for the API call
        $url = $this->buildURL(
            'ApproveAssignment',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'ApproveAssignmentResult');
    }

    /**
     * Rejects the results of a completed assignment.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_RejectAssignmentOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function rejectAssignment($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['AssignmentId'];
        $optional = ['RequesterFeedback'];

        // build the URL for the API call
        $url = $this->buildURL(
            'RejectAssignment',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'RejectAssignmentResult');
    }

    /**
     * Approves an assignment that was previously rejected.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_ApproveRejectedAssignmentOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function approveRejectedAssignment($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['AssignmentId'];
        $optional = ['RequesterFeedback'];

        // build the URL for the API call
        $url = $this->buildURL(
            'ApproveRejectedAssignment',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'ApproveRejectedAssignmentResult');
    }

    /**
     * Generates and returns a temporary URL which can be used to retrieve a file uploaded by a Worker as an answer to a 'FileUploadAnswer'-type question for a HIT.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetFileUploadURLOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getFileUploadURL($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['AssignmentId', 'QuestionIdentifier'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetFileUploadURL',
            $params,
            $required
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetFileUploadURLResult');
    }

    /**
     * Update the status of a HIT, either from 'Reviewable' to 'Reviewing' or 'Reviewing' to 'Reviewable'.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_SetHITAsReviewingOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function setHITAsReviewing($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['HITId'];
        $optional = ['Revert'];

        // build the URL for the API call
        $url = $this->buildURL(
            'SetHITAsReviewing',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'SetHITAsReviewingResult');
    }

    /**
     * Extends the expiration date or increases the maximum number of assignments for an existing HIT.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_ExtendHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function extendHIT($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['HITId'];
        $optional = ['MaxAssignmentsIncrement', 'ExpirationIncrementInSeconds', 'UniqueRequestToken'];

        // build the URL for the API call
        $url = $this->buildURL(
            'ExtendHIT',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'ExtendHITResult');
    }

    /**
     * Causes the HIT to expire immediately.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_ForceExpireHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function forceExpireHIT($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['HITId'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'ForceExpireHIT',
            $params,
            $required
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'ForceExpireHITResult');
    }

    /**
     * Removes a HIT from Mechanical Turk and disposes of all assignment data.
     * Will not work on HITs in a 'Reviewable' state (use disposeHIT for that).
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_DisableHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function disableHIT($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['HITId'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'DisableHIT',
            $params,
            $required
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'DisableHITResult');
    }

    /**
     * Disposes of HITs in the 'Reviewable' state.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_DisposeHITOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function disposeHIT($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['HITId'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'DisposeHIT',
            $params,
            $required
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'DisposeHITResult');
    }

    /**
     * Prompts Amazon Mechanical Turk to send a test notification message according to the provided notification parameters and specified event.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_SendTestEventNotificationOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function sendTestEventNotification($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['TestEventType'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'SendTestEventNotification',
            $params,
            $required,
            $optional,
            [
                $this->generateNotificationParameters($params),
            ]
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'SendTestEventNotificationResult');
    }

    /**
     * Sends an email to one or more Workers that you specify with the Worker ID.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_NotifyWorkersOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function notifyWorkers($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['Subject', 'MessageText', 'WorkerId'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'NotifyWorkers',
            $params,
            $required,
            $optional,
            [
                $this->generateNotificationParameters($params),
            ]
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'NotifyWorkersResult');
    }

    /**
     * Issues a payment of money from your account to a Worker.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GrantBonusOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function grantBonus($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['WorkerId', 'AssignmentId', 'BonusAmount', 'Reason'];
        $optional = ['UniqueRequestToken'];

        // build the URL for the API call
        $url = $this->buildURL(
            'GrantBonus',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GrantBonusResult');
    }

    /**
     * Retrieves the amounts of bonuses you have paid to Workers for a given HIT or assignment.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetBonusPaymentsOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getBonusPayments($params = [])
    {
        // define required and optional parameters for this API request
        $required = [];
        $optional = ['HITId', 'AssignmentId', 'PageSize', 'PageNumber'];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetBonusPayments',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetBonusPaymentsResult');
    }

    /**
     * Retrieves the amount of money in your Amazon Mechanical Turk account.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetAccountBalanceOperation.html.
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getAccountBalance()
    {
        // build the URL for the API call
        $url = $this->buildURL(
            'GetAccountBalance'
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetAccountBalanceResult');
    }

    /**
     * Retrieves statistics about the Requester.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetRequesterStatisticOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getRequesterStatistic($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['Statistic', 'TimePeriod'];
        $optional = ['Count'];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetRequesterStatistic',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetStatisticResult');
    }

    /**
     * Retrieves statistics about a specific Worker who has completed HITs for your Requester account.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetRequesterWorkerStatisticOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getRequesterWorkerStatistic($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['Statistic', 'WorkerId', 'TimePeriod'];
        $optional = ['Count'];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetRequesterWorkerStatistic',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetStatisticResult');
    }

    /**
     * Prevents a Worker from working on your HITs.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_BlockWorkerOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function blockWorker($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['WorkerId', 'Reason'];
        $optional = [];

        // build the URL for the API call
        $url = $this->buildURL(
            'UnblockWorker',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'UnblockWorkerResult');
    }

    /**
     * Reinstates a blocked Worker to work on your HITs.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_UnblockWorkerOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function unblockWorker($params = [])
    {
        // define required and optional parameters for this API request
        $required = ['WorkerId'];
        $optional = ['Reason'];

        // build the URL for the API call
        $url = $this->buildURL(
            'UnblockWorker',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'UnblockWorkerResult');
    }

    /**
     * Retrieves a list of Workers who are blocked from working on your HITs.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_GetBlockedWorkersOperation.html.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws LaraTurkException
     */
    public function getBlockedWorkers($params = [])
    {
        // define required and optional parameters for this API request
        $required = [];
        $optional = ['PageNumber', 'PageSize'];

        // build the URL for the API call
        $url = $this->buildURL(
            'GetBlockedWorkers',
            $params,
            $required,
            $optional
        );

        // submit query to constructed URL
        $response = $this->guzzle->get($url);

        // if response succeeded, return the decoded response
        return $this->processAPIResponse($response, 'GetBlockedWorkersResult');
    }

    /*
     |--------------------------------------------------------------------------
     | Private Functions
     |--------------------------------------------------------------------------
     */

    /**
     * Builds the first part of the Mechanical Turk API URL common to all requests.
     *
     * @param string $operation
     *
     * @return string
     */
    private function startUrl($operation)
    {
        $time = $this->Unix2UTC(time());

        $url = $this->endpoint;
        $url .= '?Service='.$this->MTURK_SERVICE;
        $url .= '&AWSAccessKeyId='.urlencode($this->aws_access_key);
        $url .= '&Version='.'2014-08-15';
        $url .= '&Operation='.$operation;
        $url .= '&Signature='.urlencode($this->generateSignature($this->MTURK_SERVICE, $operation, $time));
        $url .= '&Timestamp='.urlencode($time);

        return $url;
    }

    /**
     * Builds up the URL to which the API call is made.
     *
     * @param string $operation
     * @param array  $params
     * @param array  $required
     * @param array  $optional
     * @param array  $raws
     *
     * @return string
     *
     * @throws LaraTurkException if a required parameter is not found
     */
    private function buildURL($operation, $params = [], $required = [], $optional = [], $raws = [])
    {
        $url = $this->startUrl($operation);

        foreach ($required as $key) {
            $this->checkParamIsPresent($key, $params);

            $url .= '&'.$key.'='.urlencode($params[$key]);
        }

        foreach ($optional as $key) {
            if (isset($params[$key])) {
                $url .= '&'.$key.'='.urlencode($params[$key]);
            }
        }

        foreach ($raws as $raw) {
            $url .= $raw;
        }

        return $url;
    }

    /**
     * Checks if a required parameter is present in the parameters array.
     *
     * @param string $key
     * @param array  $params
     *
     * @throws LaraTurkException if passed parameter is not found
     */
    private function checkParamIsPresent($key, $params = [])
    {
        if (!isset($params[$key])) {
            throw new LaraTurkException('The '.$key.' parameter is required.');
        }
    }

    /**
     * Decodes the API response from XML to a JSON array.
     *
     * @param Response $response
     *
     * @return array
     */
    private function decodeRequest(Response $response)
    {
        // check if XML received

        $xml = $response->xml();

        return json_decode(json_encode($xml), true);
    }

    /**
     * Generates the signature AWS needs for authenticating requests.
     * See http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMechanicalTurkRequester/MakingRequests_RequestAuthenticationArticle.html
     * Taken from: http://docs.aws.amazon.com/AWSMechTurk/2006-10-31/AWSMechanicalTurkGettingStartedGuide/MakingARequest.html#d0e1432.
     *
     * @param string $service
     * @param string $operation
     * @param string $timestamp
     *
     * @return string
     */
    private function generateSignature($service, $operation, $timestamp)
    {
        $string_to_encode = $service.$operation.$timestamp;
        $hmac = $this->hmac_sha1($this->aws_secret_key, $string_to_encode);
        $signature = base64_encode($hmac);

        return $signature;
    }

    /**
     * Generates the part of the URL that specifies worker qualification requirements for the HIT.
     * See http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_QualificationRequirementDataStructureArticle.html.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws LaraTurkException if required parameter is not found
     */
    private function generateQualificationRequirement($params = [])
    {
        $this->checkParamIsPresent('QualificationRequirement', $params);

        $string = '';
        foreach ($params['QualificationRequirement'] as $i => $qual) {
            ++$i;
            $string .= '&QualificationRequirement.'.$i.'.QualificationTypeId='.urlencode($qual['QualificationTypeId']);
            $string .= '&QualificationRequirement.'.$i.'.Comparator='.urlencode($qual['Comparator']);
            if (isset($qual['IntegerValue'])) {
                $string .= '&QualificationRequirement.'.$i.'.IntegerValue='.urlencode($qual['IntegerValue']);
            }
            if (isset($qual['LocaleValue'])) {
                foreach ($qual['LocaleValue'] as $z => $loc) {
                    ++$z;
                    $string .= '&QualificationRequirement.'.$i.'.LocaleValue.'.$z.'.Country='.urlencode($loc['Country']);
                    if (isset($loc['Subdivision'])) {
                        $string .= '&QualificationRequirement.'.$i.'.LocalValue.'.$z.'.Subdivision='.urlencode($loc['Subdivision']);
                    }
                }
            }
            if (isset($qual['RequiredToPreview'])) {
                $string .= '&QualificationRequirement.'.$i.'.RequiredToPreview='.urlencode($qual['RequiredToPreview']);
            }
        }

        return  $string;
    }

    /**
     * Generates the part of the URL that specifies layout parameters for the specified HITLayoutId.
     * See http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_HITLayoutParameterArticle.html.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws LaraTurkException if required parameter is not found
     */
    private function generateHITLayoutParameters($params = [])
    {
        $this->checkParamIsPresent('HITLayoutParameter', $params);

        $string = '';
        foreach ($params['HITLayoutParameter'] as $i => $param) {
            ++$i;
            $string .= '&HITLayoutParameter.'.$i.'.Name='.urlencode($param['Name']);
            $string .= '&HITLayoutParameter.'.$i.'.Value='.urlencode($param['Value']);
        }

        return  $string;
    }

    /**
     * Generates the part of the URL that specifies the Reward parameter.
     * See: http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_PriceDataStructureArticle.html.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws LaraTurkException if required parameter is not found
     */
    private function generateReward($params = [])
    {
        $this->checkParamIsPresent('Reward', $params);

        $string = '&Reward.1.Amount='.$params['Reward']['Amount'];
        $string .= '&Reward.1.CurrencyCode='.$params['Reward']['CurrencyCode'];
        if (isset($params['Reward']['FormattedPrice'])) {
            $string .= '&Reward.1.FormattedPrice='.$params['Reward']['FormattedPrice'];
        }

        return $string;
    }

    /**
     * Generates the part of the URL that specifies the keywords parameter.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws LaraTurkException if required parameter is not found
     */
    private function generateKeywords($params = [])
    {
        $this->checkParamIsPresent('Keywords', $params);

        return '&Keywords='.urlencode(implode(',', $params['Keywords']));
    }

    /**
     * Generates the part of the URL that specifies notification parameters.
     * See http://docs.aws.amazon.com/AWSMechTurk/latest/AWSMturkAPI/ApiReference_NotificationDataStructureArticle.html.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws LaraTurkException if required parameter is not found
     */
    private function generateNotificationParameters($params = [])
    {
        $this->checkParamIsPresent('Notification', $params);

        $string = '';
        foreach ($params['Notification'] as $i => $param) {
            ++$i;
            $string .= '&Notification.'.$i.'.Destination='.urlencode($param['Destination']);
            $string .= '&Notification.'.$i.'.Transport='.urlencode($param['Transport']);
            $string .= '&Notification.'.$i.'.Version='.urlencode($param['Version']);
            if (count($param['EventType']) > 1) {
                foreach ($param['EventType'] as $z => $event) {
                    ++$z;
                    $string .= '&Notification.'.$z.'.EventType='.urlencode($event);
                }
            } else {
                $string .= '&Notification.'.$i.'.EventType='.urlencode($param['EventType']);
            }
        }

        return $string;
    }

    /**
     * Checks if the API call succeeded.
     * If so, decode the response and return it.
     * If not, determine what error occurred.
     *
     * @param Response $response
     * @param string   $result
     *
     * @return array $decode
     *
     * @throws LaraTurkException if the API call did not succeed
     */
    private function processAPIResponse(Response $response, $result)
    {
        // decode the XML response body
        $decode = $this->decodeRequest($response);

        if ($response->getStatusCode() == 200 and
             isset($decode[$result]['Request']['IsValid']) and
             $decode[$result]['Request']['IsValid'] == 'True') {
            return $decode;
        }

        $this->determineError($decode, $result);
    }

    /**
     * Determines what kind of error was returned from the Amazon Mechanical Turk API.
     *
     * @param array  $decode
     * @param string $result The expected response element
     *
     * @throws LaraTurkException
     */
    private function determineError($decode, $result)
    {
        if (isset($decode['OperationRequest']['Errors']['Error']['Code']) and
             $decode['OperationRequest']['Errors']['Error']['Code'] = 'AWS.NotAuthorized') {
            // AWS credentials were rejected
            throw new LaraTurkException('AWS credentials rejected.', $decode['OperationRequest']['Errors']);
        }

        if (isset($decode[$result]['Request']['Errors']['Error'])) {
            // Request failed due to other factors (e.g., malformed request, insufficient funds)
            throw new LaraTurkException('Request returned error. See errors for context.', $decode[$result]['Request']['Errors']);
        }

        throw new LaraTurkException('Request returned error. No context available.');
    }

    /**
     * Creates the HMAC for generating the API signature.
     *
     * @param string $key    The key to use for the encryption
     * @param string $string The string to encrypt
     *
     * @return string
     */
    private function hmac_sha1($key, $string)
    {
        return pack('H*', sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))).
            pack('H*', sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))).$string))));
    }

    /**
     * Takes a UNIX timestamp and returns a timestamp in the format YYYY-MM-DDTHH:mm:ssZ.
     * Example Output: 1989-01-09T12:12:12Z.
     *
     * @param $time
     *
     * @return string
     */
    private function Unix2UTC($time)
    {
        return date('Y-m-d\TH:i:s', $time).'Z';
    }
}
