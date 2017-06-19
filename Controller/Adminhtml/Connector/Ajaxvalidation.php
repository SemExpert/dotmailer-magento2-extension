<?php

namespace Dotdigitalgroup\Email\Controller\Adminhtml\Connector;

class Ajaxvalidation extends \Magento\Backend\App\Action
{
    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    private $data;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    private $jsonHelper;
    /**
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    /**
     * Ajaxvalidation constructor.
     *
     * @param \Dotdigitalgroup\Email\Helper\Data $data
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Escaper $escaper
     */
    public function __construct(
        \Dotdigitalgroup\Email\Helper\Data $data,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Escaper $escaper
    ) {
        $this->data = $data;
        $this->jsonHelper = $jsonHelper;
        $this->escaper = $escaper;
        parent::__construct($context);
    }

    /**
     * Validate api user.
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $apiUsername = $this->escaper->escapeHtml($params['api_username']);
        $apiPassword = base64_decode(
            $this->escaper->escapeHtml($params['api_password'])
        );
        //validate api, check against account info.
        if ($this->data->isEnabled()) {
            $client = $this->data->getWebsiteApiClient();
            $result = $client->validate($apiUsername, $apiPassword);

            $resonseData['success'] = true;
            //validation failed
            if (!$result) {
                $resonseData['success'] = false;
                $resonseData['message'] = 'Authorization has been denied for this request.';
            }

            $this->getResponse()->representJson($this->jsonHelper->jsonEncode($resonseData));
        }
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Dotdigitalgroup_Email::config');
    }
}
