<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  MEN AT WORK 2012
 * @package    clipboard
 * @license    GNU/GPL 2
 * @filesource
 */
require_once TL_ROOT . '/system/drivers/DC_Table.php';

class DC_Clipboard extends DC_Table
{

    /**
     * Recursively generate the tree and return it as HTML string
     * @param string
     * @param integer
     * @param array
     * @param boolean
     * @param integer
     * @param array
     * @param boolean
     * @param boolean
     * @return string
     */
    protected function generateTree($table, $id, $arrPrevNext, $blnHasSorting, $intMargin = 0, $arrClipboard = false, $blnCircularReference = false, $protectedPage = false)
    {
        static $session;

        $session = $this->Session->getData();
        $node = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $this->strTable . '_' . $table . '_tree' : $this->strTable . '_tree';

        // Toggle nodes
        if ($this->Input->get('ptg'))
        {
            $session[$node][$this->Input->get('ptg')] = (isset($session[$node][$this->Input->get('ptg')]) && $session[$node][$this->Input->get('ptg')] == 1) ? 0 : 1;
            $this->Session->setData($session);

            $this->redirect(preg_replace('/(&(amp;)?|\?)ptg=[^& ]*/i', '', $this->Environment->request));
        }

        $objRow = $this->Database->prepare("SELECT * FROM " . $table . " WHERE id=?")
                ->limit(1)
                ->execute($id);

        // Return if there is no result
        if ($objRow->numRows < 1)
        {
            $this->Session->setData($session);
            return '';
        }

        $return = '';
        $intSpacing = 20;

        // Add the ID to the list of current IDs
        if ($this->strTable == $table)
        {
            $this->current[] = $objRow->id;
        }

        // Check whether there are child records
        if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 || $this->strTable != $table)
        {
            $objChilds = $this->Database->prepare("SELECT id FROM " . $table . " WHERE pid=?" . ($blnHasSorting ? " ORDER BY sorting" : ''))
                    ->execute($id);

            if ($objChilds->numRows)
            {
                $childs = $objChilds->fetchEach('id');
            }
        }

        $blnProtected = false;

        // Check whether the page is protected
        if ($table == 'tl_page')
        {
            $blnProtected = ($objRow->protected || $protectedPage) ? true : false;
        }

        $session[$node][$id] = (is_int($session[$node][$id])) ? $session[$node][$id] : 0;
        $mouseover = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 || $table == $this->strTable) ? ' onmouseover="Theme.hoverDiv(this, 1);" onmouseout="Theme.hoverDiv(this, 0);"' : '';

        $return .= "\n  " . '<li class="' . ((($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 && $objRow->type == 'root') || $table != $this->strTable) ? 'tl_folder' : 'tl_file') . '"' . $mouseover . '><div class="tl_left" style="padding-left:' . ($intMargin + $intSpacing) . 'px;">';

        // Calculate label and add a toggle button
        $args = array();
        $folderAttribute = 'style="margin-left:20px;"';
        $showFields = $GLOBALS['TL_DCA'][$table]['list']['label']['fields'];
        $level = ($intMargin / $intSpacing + 1);

        if (count($childs))
        {
            $folderAttribute = '';
            $img = ($session[$node][$id] == 1) ? 'folMinus.gif' : 'folPlus.gif';
            $alt = ($session[$node][$id] == 1) ? $GLOBALS['TL_LANG']['MSC']['collapseNode'] : $GLOBALS['TL_LANG']['MSC']['expandNode'];
            $return .= '<a href="' . $this->addToUrl('ptg=' . $id) . '" title="' . specialchars($alt) . '" onclick="Backend.getScrollOffset(); return AjaxRequest.toggleStructure(this, \'' . $node . '_' . $id . '\', ' . $level . ', ' . $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] . ');">' . $this->generateImage($img, '', 'style="margin-right:2px;"') . '</a>';
        }

        foreach ($showFields as $k => $v)
        {
            // Decrypt the value
            if ($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['encrypt'])
            {
                $objRow->$v = deserialize($objRow->$v);

                $this->import('Encryption');
                $objRow->$v = $this->Encryption->decrypt($objRow->$v);
            }

            if (strpos($v, ':') !== false)
            {
                list($strKey, $strTable) = explode(':', $v);
                list($strTable, $strField) = explode('.', $strTable);

                $objRef = $this->Database->prepare("SELECT " . $strField . " FROM " . $strTable . " WHERE id=?")
                        ->limit(1)
                        ->execute($objRow->$strKey);

                $args[$k] = $objRef->numRows ? $objRef->$strField : '';
            }
            elseif (in_array($GLOBALS['TL_DCA'][$table]['fields'][$v]['flag'], array(5, 6, 7, 8, 9, 10)))
            {
                $args[$k] = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $objRow->$v);
            }
            elseif ($GLOBALS['TL_DCA'][$table]['fields'][$v]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['multiple'])
            {
                $args[$k] = strlen($objRow->$v) ? (strlen($GLOBALS['TL_DCA'][$table]['fields'][$v]['label'][0]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['label'][0] : $v) : '';
            }
            else
            {
                $args[$k] = strlen($GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$objRow->$v]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$objRow->$v] : $objRow->$v;
            }
        }

        $label = vsprintf(((strlen($GLOBALS['TL_DCA'][$table]['list']['label']['format'])) ? $GLOBALS['TL_DCA'][$table]['list']['label']['format'] : '%s'), $args);

        // Shorten the label it if it is too long
        if ($GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'] > 0 && $GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'] < utf8_strlen(strip_tags($label)))
        {
            $this->import('String');
            $label = trim($this->String->substrHtml($label, $GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'])) . ' …';
        }

        $label = preg_replace('/\(\) ?|\[\] ?|\{\} ?|<> ?/i', '', $label);

        // Call label_callback ($row, $label, $this)
        if (is_array($GLOBALS['TL_DCA'][$table]['list']['label']['label_callback']))
        {
            $strClass = $GLOBALS['TL_DCA'][$table]['list']['label']['label_callback'][0];
            $strMethod = $GLOBALS['TL_DCA'][$table]['list']['label']['label_callback'][1];

            $this->import($strClass);
            $return .= $this->$strClass->$strMethod($objRow->row(), $label, $this, $folderAttribute);
        }
        else
        {
            $return .= $this->generateImage('iconPLAIN.gif', '', $folderAttribute) . ' ' . $label;
        }

        $return .= '</div> <div class="tl_right">';
        $previous = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $arrPrevNext['pp'] : $arrPrevNext['p'];
        $next = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $arrPrevNext['nn'] : $arrPrevNext['n'];
        $_buttons = '';

        // Regular buttons ($row, $table, $root, $blnCircularReference, $childs, $previous, $next)
        if ($this->strTable == $table)
        {
            $_buttons .= ($this->Input->get('act') == 'select') ? '<input type="checkbox" name="IDS[]" id="ids_' . $id . '" class="tl_tree_checkbox" value="' . $id . '">' : $this->generateButtons($objRow->row(), $table, $this->root, $blnCircularReference, $childs, $previous, $next);
        }

        // Added by Patrick Kahl
        // HOOK: call the hooks for independently buttons
        if (isset($GLOBALS['TL_HOOKS']['clipboardButtons']) && is_array($GLOBALS['TL_HOOKS']['clipboardButtons']))
        {
            foreach ($GLOBALS['TL_HOOKS']['clipboardButtons'] as $callback)
            {
                $this->import($callback[0]);
                $_buttons .= $this->$callback[0]->$callback[1]($this, $objRow->row(), $table, $blnCircularReference, $arrClipboard, $childs, $previous, $next);
            }
        }

        // Paste buttons
        if ($arrClipboard !== false && $this->Input->get('act') != 'select')
        {
            $_buttons .= ' ';

            // Call paste_button_callback(&$dc, $row, $table, $blnCircularReference, $arrClipboard, $childs, $previous, $next)
            if (is_array($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback']))
            {
                $strClass = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'][0];
                $strMethod = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'][1];

                $this->import($strClass);
                $_buttons .= $this->$strClass->$strMethod($this, $objRow->row(), $table, $blnCircularReference, $arrClipboard, $childs, $previous, $next);
            }
            else
            {
                $imagePasteAfter = $this->generateImage('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id), 'class="blink"');
                $imagePasteInto = $this->generateImage('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id), 'class="blink"');

                // Regular tree (on cut: disable buttons of the page all its childs to avoid circular references)
                if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5)
                {
                    $_buttons .= ($arrClipboard['mode'] == 'cut' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id'])) || (count($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['root']) && in_array($id, $this->root))) ? $this->generateImage('pasteafter_.gif', '', 'class="blink"') . ' ' : '<a href="' . $this->addToUrl('act=' . $arrClipboard['mode'] . '&amp;mode=1&amp;pid=' . $id . (!is_array($arrClipboard['id']) ? '&amp;id=' . $arrClipboard['id'] : '')) . '" title="' . specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id)) . '" onclick="Backend.getScrollOffset();">' . $imagePasteAfter . '</a> ';
                    $_buttons .= ($arrClipboard['mode'] == 'paste' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id']))) ? $this->generateImage('pasteinto_.gif', '', 'class="blink"') . ' ' : '<a href="' . $this->addToUrl('act=' . $arrClipboard['mode'] . '&amp;mode=2&amp;pid=' . $id . (!is_array($arrClipboard['id']) ? '&amp;id=' . $arrClipboard['id'] : '')) . '" title="' . specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id)) . '" onclick="Backend.getScrollOffset();">' . $imagePasteInto . '</a> ';
                }

                // Extended tree
                else
                {
                    $_buttons .= ($this->strTable == $table) ? (($arrClipboard['mode'] == 'cut' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id']))) ? $this->generateImage('pasteafter_.gif', '', 'class="blink"') : '<a href="' . $this->addToUrl('act=' . $arrClipboard['mode'] . '&amp;mode=1&amp;pid=' . $id . (!is_array($arrClipboard['id']) ? '&amp;id=' . $arrClipboard['id'] : '')) . '" title="' . specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id)) . '" onclick="Backend.getScrollOffset();">' . $imagePasteAfter . '</a> ') : '';
                    $_buttons .= ($this->strTable != $table) ? '<a href="' . $this->addToUrl('act=' . $arrClipboard['mode'] . '&amp;mode=2&amp;pid=' . $id . (!is_array($arrClipboard['id']) ? '&amp;id=' . $arrClipboard['id'] : '')) . '" title="' . specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id)) . '" onclick="Backend.getScrollOffset();">' . $imagePasteInto . '</a> ' : '';
                }
            }
        }

        $return .= (strlen($_buttons) ? $_buttons : '&nbsp;') . '</div><div style="clear:both;"></div></li>';

        // Add records of the table itself
        if ($table != $this->strTable)
        {
            $objChilds = $this->Database->prepare("SELECT id FROM " . $this->strTable . " WHERE pid=?" . ($blnHasSorting ? " ORDER BY sorting" : ''))
                    ->execute($id);

            if ($objChilds->numRows)
            {
                $ids = $objChilds->fetchEach('id');

                for ($j = 0; $j < count($ids); $j++)
                {
                    $return .= $this->generateTree($this->strTable, $ids[$j], array('pp' => $ids[($j - 1)], 'nn' => $ids[($j + 1)]), $blnHasSorting, ($intMargin + $intSpacing + 20), $arrClipboard, false, ($j < (count($ids) - 1) || count($childs)));
                }
            }
        }

        // Begin new submenu
        if (count($childs) && $session[$node][$id] == 1)
        {
            $return .= '<li class="parent" id="' . $node . '_' . $id . '"><ul class="level_' . $level . '">';
        }

        // Add records of the parent table
        if ($session[$node][$id] == 1)
        {
            if (is_array($childs))
            {
                for ($k = 0; $k < count($childs); $k++)
                {
                    $return .= $this->generateTree($table, $childs[$k], array('p' => $childs[($k - 1)], 'n' => $childs[($k + 1)]), $blnHasSorting, ($intMargin + $intSpacing), $arrClipboard, ((($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 && $childs[$k] == $arrClipboard['id']) || $blnCircularReference) ? true : false), ($blnProtected || $protectedPage));
                }
            }
        }

        // Close submenu
        if (count($childs) && $session[$node][$id] == 1)
        {
            $return .= '</ul></li>';
        }

        $this->Session->setData($session);
        return $return;
    }

}

?>