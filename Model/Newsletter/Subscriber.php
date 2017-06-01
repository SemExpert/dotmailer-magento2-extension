<?php

namespace Dotdigitalgroup\Email\Model\Newsletter;

use Magento\Framework\Exception\LocalizedException;

/**
 * Sync subscribers.
 */
class Subscriber
{
    const STATUS_SUBSCRIBED = 1;
    const STATUS_NOT_ACTIVE = 2;
    const STATUS_UNSUBSCRIBED = 3;
    const STATUS_UNCONFIRMED = 4;

    /**
     * @var
     */
    public $start;

    /**
     * Global number of subscriber updated.
     *
     * @var int
     */
    public $countSubscribers = 0;

    /**
     * @var \Dotdigitalgroup\Email\Model\ContactFactory
     */
    public $contactFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    public $orderCollection;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    public $timezone;

    public function __construct(
        \Dotdigitalgroup\Email\Model\ContactFactory $contactFactory,
        \Dotdigitalgroup\Email\Helper\Data $helper,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollection,
        \Dotdigitalgroup\Email\Model\Newsletter\SubscriberExporter $subscriberExporter,
        \Dotdigitalgroup\Email\Model\Newsletter\SubscriberWithSalesExporter $subscriberWithSalesExporter
    ) {
        $this->helper            = $helper;
        $this->contactFactory    = $contactFactory;
        $this->orderCollection   = $orderCollection;
        $this->subscriberExporter = $subscriberExporter;
        $this->subscriberWithSalesExporter = $subscriberWithSalesExporter;
    }

    /**
     * @return array
     */
    public function sync()
    {
        $response    = ['success' => true, 'message' => ''];
        $this->start = microtime(true);
        $websites    = $this->helper->getWebsites(true);

        foreach ($websites as $website) {
            //if subscriber is enabled and mapped
            $apiEnabled = $this->helper->isEnabled($website->getid());
            $subscriberEnabled
                = $this->helper->isSubscriberSyncEnabled($website->getid());
            $addressBook
                = $this->helper->getSubscriberAddressBook($website->getId());
            //enabled and mapped
            if ($apiEnabled && $addressBook && $subscriberEnabled) {
                //ready to start sync
                $numUpdated = $this->exportSubscribersPerWebsite($website);

                // show message for any number of customers
                if ($numUpdated) {
                    $response['message'] .= $website->getName() . ',  count = ' . $numUpdated;
                }
            }
        }
        //sync proccessed
        if ($this->countSubscribers) {
            $message = '----------- Subscribers sync ----------- : ' . gmdate('H:i:s', microtime(true) - $this->start) .
                ', updated = ' . $this->countSubscribers;
            $this->helper->log($message);
            $message .= $response['message'];
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Export subscribers per website.
     *
     * @param $website
     *
     * @return int
     *
     * @throws LocalizedException
     */
    public function exportSubscribersPerWebsite($website)
    {
        $updated = 0;
        $limit = $this->helper->getSyncLimit($website->getId());
        //subscriber collection to import
        $emailContactModel = $this->contactFactory->create();
        //Customer Subscribers
        $subscribersAreCustomers = $emailContactModel->getSubscribersToImport($website, $limit);
        //Guest Subscribers
        $subscribersAreGuest = $emailContactModel->getSubscribersToImport($website, $limit, false);
        $subscribersGuestEmails = $subscribersAreGuest->getColumnValues('email');
        $existInSales = [];
        if (! empty($subscribersGuestEmails)) {
            $existInSales = $this->checkInSales($subscribersGuestEmails);
        }
        $emailsNotInSales = array_diff($subscribersGuestEmails, $existInSales);
        $customerSubscribers = $subscribersAreCustomers->getColumnValues('email');
        $emailsWithNoSaleData = array_merge($emailsNotInSales, $customerSubscribers);
        //subscriber that are customer or/and the one that do not exist in sales order table.
        $subscribersWithNoSaleData = [];
        if (! empty($emailsWithNoSaleData)) {
            $subscribersWithNoSaleData = $emailContactModel
                ->getSubscribersToImportFromEmails($emailsWithNoSaleData);
        }
        if (! empty($subscribersWithNoSaleData)) {
            $updated += $this->subscriberExporter->exportSubscribers(
                $website,
                $subscribersWithNoSaleData
            );
            //add updated number for the website
            $this->countSubscribers += $updated;
        }
        //subscriber that are guest and also exist in sales order table.
        $subscribersWithSaleData = [];
        if (! empty($existInSales)) {
            $subscribersWithSaleData = $emailContactModel->getSubscribersToImportFromEmails($existInSales);
        }

        if (! empty($subscribersWithSaleData)) {
            $updated += $this->subscriberWithSalesExporter->exportSubscribersWithSales($website, $subscribersWithSaleData);
            //add updated number for the website
            $this->countSubscribers += $updated;
        }
        return $updated;
    }

    /**
     * Check emails exist in sales order table
     *
     * @param $emails
     * @return array
     */
    public function checkInSales($emails)
    {
        $collection = $this->orderCollection->create()
            ->addFieldToFilter('customer_email', ['in' => $emails]);
        return $collection->getColumnValues('customer_email');
    }
}
