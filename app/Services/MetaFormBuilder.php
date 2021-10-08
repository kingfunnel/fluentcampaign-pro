<?php

namespace FluentCampaign\App\Services;

use FluentCrm\Includes\Helpers\Arr;

class MetaFormBuilder
{
    private $fields = [];

    public function addField($field)
    {
        $this->fields[] = $field;
        return $this;
    }

    public function renderFields()
    {
        $fields = $this->fields;
        if(!$fields) {
            return '';
        }
        echo '<table class="form-table"><tbody>';
        foreach ($fields as $field) {
            if($field['type'] == 'select') {
                $this->renderSelect($field);
            }
        }
        echo '</tbody></table>';
    }

    public function renderSelect($field)
    {
        $fieldId = sanitize_key($field['name']);
        $label = '<label for="'.$fieldId.'">'.Arr::get($field, 'label', '').'</label>';
        $optionValues = (array) Arr::get($field, 'value');

        $selects = '';

        foreach (Arr::get($field, 'options', []) as $option) {
            $isSelected = in_array($option['key'], $optionValues);
            $selects .= '<option '.selected( true, $isSelected, false ).' value="'.$option['key'].'">'.$option['title'].'</option>';
        }

        $dataAtts = (array) Arr::get($field, 'data_attributes', []);

        $atts = [
            'name' => $field['name'],
            'class'=> 'fcrm_select '.Arr::get($field, 'class'),
            'id' => $fieldId
        ];

        if(Arr::get($field, 'multi')) {
            $atts['multiple'] = 'multiple';
        }

        $atts = array_unique(array_merge($dataAtts, $atts));

        $attributes = '';
        foreach ($atts as $attKey => $attValue) {
            $attributes .= esc_html($attKey).'="'.esc_html($attValue).'" ';
        }

        $input = '<select '.$attributes.'>'.$selects.'</select>';

        if($description = Arr::get($field, 'desc')) {
            $input .= '<p class="description">'.esc_html($description).'</p>';
        }
        $this->renderFieldBase($label, $input);
    }

    public function renderFieldBase($label, $input)
    {
        ?>
        <tr>
            <th scope="row"><?php echo $label; ?></th>
            <td><?php echo $input; ?></td>
        </tr>
        <?php
    }
}
