<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * A setting whose comment is a warning: the comment is shown in the warning colour
 */
class WarningCommentField extends Field
{
    /**
     * Render the value with its comment marked as a warning
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _renderValue(AbstractElement $element)
    {
        $comment = $element->getData('comment');
        $html = '<td class="value">' . $this->_getElementHtml($element);
        $comment = is_scalar($comment) || $comment instanceof \Stringable ? (string) $comment : '';
        if ($comment !== '') {
            $html .= '<p class="note hryvinskyi-datalayer-note--warning"><span>' . $comment . '</span></p>';
        }

        return $html . '</td>';
    }
}
