<?php

declare(strict_types=1);

namespace Xtreme\AttributeFiltering\Controller\Adminhtml\Option;

use Magento\Backend\App\Action;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeOptionManagementInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Swatches\Model\SwatchAttributeType;

class Create extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    private const TYPE_DROPDOWN = 'dropdown';
    private const TYPE_TEXT = 'text';
    private const TYPE_VISUAL = 'visual';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly ProductAttributeOptionManagementInterface $optionManagement,
        private readonly AttributeOptionInterfaceFactory $optionFactory,
        private readonly OptionCollectionFactory $optionCollectionFactory,
        private readonly SwatchAttributeType $swatchAttributeType
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $label = trim((string)$this->getRequest()->getParam('label', ''));
            $requestedType = trim((string)$this->getRequest()->getParam('attribute_type', ''));
            $swatchValue = trim((string)$this->getRequest()->getParam('swatch_value', ''));
            $attribute = $this->resolveAttribute();
            $attributeCode = (string)$attribute->getAttributeCode();

            if ($label === '') {
                throw new LocalizedException(__('Attribute option label is required.'));
            }
            $this->validateAttribute($attribute, $requestedType);

            $sortOrder = $this->getNextSortOrder((int)$attribute->getAttributeId());
            $option = $this->optionFactory->create();
            $option->setLabel($label);
            $option->setSortOrder($sortOrder);
            $option->setValue($this->getOptionValue($attribute, $swatchValue, $label));

            $optionId = $this->optionManagement->add($attributeCode, $option);

            return $result->setData([
                'error' => false,
                'message' => __(
                    '"%1" added with sort order %2. Use attribute management to customize this option.',
                    $label,
                    $sortOrder
                ),
                'option' => [
                    'value' => (string)$optionId,
                    'label' => $label,
                ],
                'sort_order' => $sortOrder,
            ]);
        } catch (LocalizedException $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'error' => true,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            return $result->setHttpResponseCode(500)->setData([
                'error' => true,
                'message' => __('The option could not be created.'),
            ]);
        }
    }

    private function resolveAttribute(): ProductAttributeInterface
    {
        $candidates = array_filter(array_unique([
            $this->sanitizeAttributeCode((string)$this->getRequest()->getParam('attribute_code', '')),
            $this->extractAttributeCodeFromScope((string)$this->getRequest()->getParam('data_scope', '')),
            $this->sanitizeAttributeCode((string)$this->getRequest()->getParam('field_index', '')),
        ]));

        foreach ($candidates as $candidate) {
            try {
                return $this->attributeRepository->get($candidate);
            } catch (NoSuchEntityException) {
                continue;
            }
        }

        throw new LocalizedException(__('The attribute could not be resolved from the product form request.'));
    }

    private function sanitizeAttributeCode(string $attributeCode): string
    {
        $attributeCode = trim($attributeCode);

        if ($attributeCode === '' || $attributeCode === ProductAttributeInterface::ENTITY_TYPE_CODE) {
            return '';
        }

        return $attributeCode;
    }

    private function extractAttributeCodeFromScope(string $dataScope): string
    {
        $dataScope = trim($dataScope);
        if ($dataScope === '') {
            return '';
        }

        $parts = explode('.', $dataScope);

        return $this->sanitizeAttributeCode((string)end($parts));
    }

    private function validateAttribute(ProductAttributeInterface $attribute, string $requestedType): void
    {
        if (!$attribute->usesSource() || $attribute->getFrontendInput() !== 'select') {
            throw new LocalizedException(__('This attribute does not support selectable options.'));
        }

        if (!(bool)$attribute->getIsUserDefined()) {
            throw new LocalizedException(__('Only custom attributes can be updated from the product form.'));
        }

        $actualType = $this->getAttributeType($attribute);
        if (!in_array($actualType, [self::TYPE_DROPDOWN, self::TYPE_TEXT, self::TYPE_VISUAL], true)) {
            throw new LocalizedException(__('This attribute type is not supported.'));
        }

        if ($requestedType !== '' && $requestedType !== $actualType) {
            throw new LocalizedException(__('The requested attribute type does not match the actual attribute configuration.'));
        }
    }

    private function getAttributeType(ProductAttributeInterface $attribute): string
    {
        if ($this->swatchAttributeType->isVisualSwatch($attribute)) {
            return self::TYPE_VISUAL;
        }

        if ($this->swatchAttributeType->isTextSwatch($attribute)) {
            return self::TYPE_TEXT;
        }

        return self::TYPE_DROPDOWN;
    }

    private function getOptionValue(ProductAttributeInterface $attribute, string $swatchValue, string $label): string
    {
        if ($this->swatchAttributeType->isVisualSwatch($attribute)) {
            $normalized = strtoupper($swatchValue);
            if (!preg_match('/^#(?:[0-9A-F]{3}|[0-9A-F]{6})$/', $normalized)) {
                throw new LocalizedException(__('Enter a valid hex color like #336699.'));
            }

            return $normalized;
        }

        if ($this->swatchAttributeType->isTextSwatch($attribute)) {
            return $swatchValue !== '' ? $swatchValue : $label;
        }

        return '';
    }

    private function getNextSortOrder(int $attributeId): int
    {
        $collection = $this->optionCollectionFactory->create()->setAttributeFilter($attributeId);
        $maxSortOrder = 0;

        foreach ($collection as $item) {
            $maxSortOrder = max($maxSortOrder, (int)$item->getSortOrder());
        }

        return $maxSortOrder + 1;
    }
}
