<?php

declare(strict_types=1);

namespace Xtreme\AttributeFiltering\Plugin\Catalog\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\UrlInterface;
use Magento\Swatches\Model\SwatchAttributeType;

class EavPlugin
{
    private const CONFIG_PATH = 'arguments/data/config';
    private const COMPONENT = 'Xtreme_AttributeFiltering/js/form/element/search-select';
    private const ELEMENT_TMPL = 'Xtreme_AttributeFiltering/form/element/search-select';
    private const TYPE_DROPDOWN = 'dropdown';
    private const TYPE_TEXT = 'text';
    private const TYPE_VISUAL = 'visual';

    public function __construct(
        private readonly ArrayManager $arrayManager,
        private readonly UrlInterface $urlBuilder,
        private readonly SwatchAttributeType $swatchAttributeType
    ) {
    }

    public function afterSetupAttributeMeta(
        \Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav $subject,
        array $result,
        ProductAttributeInterface $attribute,
        string $groupCode,
        int $sortOrder
    ): array {
        if (empty($result)
            || $attribute->getFrontendInput() !== 'select'
            || !(bool)$attribute->getIsUserDefined()
        ) {
            return $result;
        }

        $config = $this->arrayManager->get(self::CONFIG_PATH, $result, []);

        if (($config['formElement'] ?? null) !== 'select' || empty($config['options'])) {
            return $result;
        }

        return $this->arrayManager->merge(
            self::CONFIG_PATH,
            $result,
            [
                'component' => self::COMPONENT,
                'elementTmpl' => self::ELEMENT_TMPL,
                'searchPlaceholder' => __('Type to filter options'),
                'noMatchesMessage' => __('No matches found'),
                'createOptionUrl' => $this->urlBuilder->getUrl('xtreme_attributefiltering/option/create'),
                'attributeCode' => $attribute->getAttributeCode(),
                'attributeFilteringType' => $this->getAttributeType($attribute)
            ]
        );
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
}
