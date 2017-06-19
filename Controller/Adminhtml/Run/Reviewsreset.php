<?php

namespace Dotdigitalgroup\Email\Controller\Adminhtml\Run;

class Reviewsreset extends \Magento\Backend\App\AbstractAction
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;
    /**
     * @var \Dotdigitalgroup\Email\Model\ResourceModel\ReviewFactory
     */
    private $reviewFactory;
    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    private $helper;
    /**
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    /**
     * Reviewsreset constructor.
     *
     * @param \Dotdigitalgroup\Email\Model\ResourceModel\ReviewFactory $reviewFactory
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Dotdigitalgroup\Email\Helper\Data $data
     */
    public function __construct(
        \Dotdigitalgroup\Email\Model\ResourceModel\ReviewFactory $reviewFactory,
        \Magento\Backend\App\Action\Context $context,
        \Dotdigitalgroup\Email\Helper\Data $data,
        \Magento\Framework\Escaper $escaper
    ) {
        $this->reviewFactory  = $reviewFactory;
        $this->messageManager = $context->getMessageManager();
        $this->helper         = $data;
        $this->escaper        = $escaper;
        parent::__construct($context);
    }

    /**
     * Refresh suppressed contacts.
     */
    public function execute()
    {
        $params = $this->escaper->escapeHtml($this->getRequest()->getParams());
        if ($params['from'] && $params['to']) {
            $error = $this->helper->validateDateRange(
                $params['from'],
                $params['to']
            );
            if (is_string($error)) {
                $this->messageManager->addErrorMessage($error);
            } else {
                $this->reviewFactory->create()
                    ->resetReviews($params['from'], $params['to']);
                $this->messageManager->addSuccessMessage(__('Done.'));
            }
        } else {
            $this->reviewFactory->create()
                ->resetReviews();
            $this->messageManager->addSuccessMessage(__('Done.'));
        }

        $redirectUrl = $this->getUrl(
            'adminhtml/system_config/edit',
            ['section' => 'connector_developer_settings']
        );
        $this->_redirect($redirectUrl);
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Dotdigitalgroup_Email::config');
    }
}
