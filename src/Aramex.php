<?php

namespace Octw\Aramex;

use Octw\Aramex\Core;
use Octw\Aramex\Helpers\AramexHelper;
use SoapClient;

/**
 * The Package Interface that will be used in App\Http\ 
 */
class Aramex
{

    /**
     *
     *  @param array of pickup parameters
     *  @return object described in https://
     */
    public static function createPickup($param = [])
    {
        // Define an instance from the core class.
        $aramex = new Core;
        // Import SoapCLient object from Aramex's endpoint.

        $soapClient = AramexHelper::getSoapClient(AramexHelper::SHIPPING);


        // Preparation for initializing pickup request (Extract the data). 
        $pickupAddress = AramexHelper::extractPickupAddressContact($param);     // unchangeable
        $pickupDetails = AramexHelper::extractPickupDetails($param);            // changeable

        // initialize pickup request.
        $aramex->initializePickup($pickupDetails, $pickupAddress);

        // call the SoapClient API.
        $call = $soapClient->CreatePickup($aramex->getParam());

        $ret = new \stdClass;
        // check the response.
        if ($call->HasErrors) {

            // prepare return object with errors described in call response.
            $ret->error = 1;
            // No one knows what is the structure of the response 
            if (is_array($call->Notifications)) {
                $ret->errors = $call->Notifications['Notification'];
            } else {
                $ret->errors = $call->Notifications->Notification;
            }
        } else {

            // extract helpful data from call response.
            $pickupGUID = $call->ProcessedPickup->GUID;
            $pickupId = $call->ProcessedPickup->ID;
            //Extra Stuffs TODO.


            // Prepare return object.
            $ret->error = 0;
            $ret->pickupGUID = $pickupGUID;
            $ret->pickupID   = $pickupId;
        }
        // return the prepared object.
        return $ret;
    }

    public static function cancelPickup($pickupGuid, $commnet)
    {
        // Define an instance from the core class.
        $aramex = new Core;

        // Import SoapCLient object from Aramex's endpoint. 
        $soapClient = AramexHelper::getSoapClient(AramexHelper::SHIPPING);


        $aramex->initializePickupCancelation($pickupGuid, $commnet);

        $call = $soapClient->CancelPickup($aramex->getParam());

        $ret = new \stdClass;

        if ($call->HasErrors) {
            $ret->error = 1;
            if (is_array($call->Notifications)) {
                $ret->errors = $call->Notifications['Notification'];
            } else {
                $ret->errors = $call->Notifications->Notification;
            }
        } else {
            $ret = $call;
        }
        return $ret;
    }


    /**
     *
     * @param array of shipment parameters 
     * @return object described in https://
     **/
    public static function createShipment($param = [])
    {
        // Define an instance from the core class.
        $aramex = new Core;
        // Import SoapCLient object from Aramex's endpoint. 

        $soapClient = AramexHelper::getSoapClient(AramexHelper::SHIPPING);

        $shipperAddress = AramexHelper::extractShipperAddressContact($param);
        $consigneeAddress = AramexHelper::extractConsigneeAddressContact($param);

        $shipmentDetails = AramexHelper::extractShipmentDetails($param);

        $aramex->initializeShipment($shipperAddress, $consigneeAddress, $shipmentDetails);

        $call =  $soapClient->CreateShipments($aramex->getParam());

        $ret = new \stdClass;

        if ($call->HasErrors) {
            $ret->error = 1;
            if (isset($call->Notifications->Notification)) {
                $ret->errors = [$call->Notifications->Notification];
            }

            if (is_object($call->Shipments->ProcessedShipment->Notifications->Notification)) {
                $ret->errors = [$call->Shipments->ProcessedShipment->Notifications->Notification];
            } else {
                $ret->errors = $call->Shipments->ProcessedShipment->Notifications->Notification;
            }
        } else {
            $ret = $call;
        }

        return $ret;
    }



    public static function calculateRate($origin, $destination, $shipmentDetails, $currency)
    {

        $aramex = new Core;

        $soapClient = AramexHelper::getSoapClient(AramexHelper::RATE);


        $destinationAddress = AramexHelper::extractAddress($destination);

        $originAddress = AramexHelper::extractAddress($origin);

        $details = AramexHelper::extractCalculateRateShipmentDetails($shipmentDetails);

        $aramex->initializeCalculateRate($originAddress, $destinationAddress, $details, $currency);

        $call =  $soapClient->calculateRate($aramex->getParam());

        $ret = new \stdClass;

        if ($call->HasErrors) {
            $ret->error = 1;
            $ret->errors = $call->Notifications;
        } else {
            $ret = $call;
        }

        return $ret;
    }


    public static function trackShipments($param)
    {
        if (!is_array($param)) {
            throw new \Exception("trackShipments Parameter Should Be an Array includes Strings", 1);
        }

        foreach ($param as $shipmentId) {
            if (!is_string($shipmentId)) {
                throw new \Exception("trackShipments Parameter Should Be an Array includes Strings", 1);
            }
        }

        $soapClient = AramexHelper::getSoapClient(AramexHelper::TRACKING);


        $aramex = new Core;

        $aramex->initializeShipmentTracking($param);

        $call = $soapClient->TrackShipments($aramex->getParam());

        $ret = new \stdClass;

        if ($call->HasErrors) {
            $ret->error = 1;
            $ret->errors = $call->Notifications;
        } else {
            $ret = $call;
        }

        return $ret;
    }


    /**
     * Put one or more already-created shipments on hold.
     *
     * Aramex's Shipping API has no operation to delete/cancel a created AWB;
     * HoldShipments is the supported way to stop a shipment after creation.
     * To cancel before collection, cancel the pickup via cancelPickup() instead.
     *
     * @param array $shipmentNumbers array of shipment (AWB) numbers as strings
     * @param string $comments reason for holding the shipment(s)
     */
    public static function holdShipments($shipmentNumbers, $comments = '')
    {
        if (!is_array($shipmentNumbers)) {
            throw new \Exception("holdShipments Parameter Should Be an Array includes Strings", 1);
        }

        foreach ($shipmentNumbers as $shipmentId) {
            if (!is_string($shipmentId)) {
                throw new \Exception("holdShipments Parameter Should Be an Array includes Strings", 1);
            }
        }

        $soapClient = AramexHelper::getSoapClient(AramexHelper::SHIPPING);

        $aramex = new Core;

        $aramex->initializeHoldShipments($shipmentNumbers, $comments);

        $call = $soapClient->HoldShipments($aramex->getParam());

        $ret = new \stdClass;

        if ($call->HasErrors) {
            $ret->error = 1;
            $ret->errors = [];

            // Request-level errors live in the top-level Notifications.
            if (isset($call->Notifications->Notification)) {
                $notifications = $call->Notifications->Notification;
                $ret->errors = array_merge($ret->errors, is_array($notifications) ? $notifications : [$notifications]);
            }

            // Per-shipment errors live inside each ProcessedShipmentHold.
            if (isset($call->ProcessedShipmentHolds->ProcessedShipmentHold)) {
                $holds = $call->ProcessedShipmentHolds->ProcessedShipmentHold;
                $holds = is_array($holds) ? $holds : [$holds];

                foreach ($holds as $hold) {
                    if (empty($hold->HasErrors) || !isset($hold->Notifications->Notification)) {
                        continue;
                    }

                    $holdNotifications = $hold->Notifications->Notification;
                    $holdNotifications = is_array($holdNotifications) ? $holdNotifications : [$holdNotifications];

                    foreach ($holdNotifications as $notification) {
                        $notification->ShipmentNumber = $hold->ID ?? null;
                        $ret->errors[] = $notification;
                    }
                }
            }
        } else {
            $ret = $call;
        }

        return $ret;
    }


    public static function fetchCountries($code = null)
    {

        $soapClient = AramexHelper::getSoapClient(AramexHelper::LOCATION);

        $aramex = new Core;

        $aramex->initializeFetchCountries($code);

        if (isset($code))
            $call = $soapClient->FetchCountry($aramex->getParam());
        else
            $call = $soapClient->FetchCountries($aramex->getParam());

        $ret = new \stdClass;

        if ($call->HasErrors) {
            $ret->error = 1;
            $ret->errors = $call->Notification;
        } else {
            $ret = $call;
        }

        return $ret;
    }

    public static function fetchCities($code, $nameStartWith = null)
    {
        $soapClient = AramexHelper::getSoapClient(AramexHelper::LOCATION);

        $aramex = new Core;

        $aramex->initializeFetchCities($code, $nameStartWith);

        $call = $soapClient->FetchCities($aramex->getParam());

        $ret = new \stdClass;

        if ($call->HasErrors) {
            $ret->error = 1;
            $ret->errors = $call->Notifications;
        } else {
            $ret = $call;
        }

        return $ret;
    }

    public static function validateAddress($address)
    {
        $address = AramexHelper::extractAddress($address);


        $soapClient = AramexHelper::getSoapClient(AramexHelper::LOCATION);

        $aramex = new Core;

        $aramex->initializeValidateAddress($address);

        $call = $soapClient->ValidateAddress($aramex->getParam());
        $ret = new \stdClass;

        if ($call->HasErrors) {
            $ret->error = 1;
            $ret->errors = $call->Notifications;
        } else {
            $ret = $call;
        }

        return $ret;
    }
}
