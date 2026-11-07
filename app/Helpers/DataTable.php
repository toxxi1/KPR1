<?php

function getDataTableActions($actions)
{
    $out = '<div class="btn-group btn-group-sm dataTable-btns" role="group">';
    foreach ($actions ?? [] as $action) {
        $bsText = $btnContent = '';
        if (in_array($action['action'], ['link', 'show'])) {
            $tag = 'a';
        } else {
            $tag = $action['tag'] ?? 'button';
        }
        $attrs = [];
        $attrs[] = !empty($action['href']) ? 'href="' . $action['href'] . '"' : '';
        $classes = [];
        $other = '';
        switch ($action['action']) {
            case 'show':
                $btnContent = '<span style="vertical-align: middle;">Просмотр</span>';
                $classes[] = 'btn-outline-gray-secondary';
                break;
            case 'link':
                $bsText = $action['text'] ?? ' - ';
                $btnContent = '<span style="vertical-align: middle;">' . $bsText . '</span>';
                $classes[] = 'btn-outline-gray-secondary';
                break;
            case 'update_employee_status':
                $bsText = 'Обновить статус сотрудника';
                $btnContent = '<span class="material-symbols-outlined text-primary">sync</span>';
                $classes[] = 'btn-outline-gray-secondary update_employee_status';
                break;
            case 'update_retail_prices':
                $bsText = 'Обновить цены и остатки на сайте';
                $btnContent = '<span class="material-symbols-outlined text-primary">sync</span>';
                $classes[] = 'btn-outline-gray-secondary update_employee_status';
                break;
            case 'generate_new_password':
                $bsText = 'Сгенерировать новый пароль';
                $btnContent = '<span class="material-symbols-outlined text-primary">sync</span>';
                $classes[] = 'btn-outline-gray-secondary';
                $other = 'onclick="return window.confirm(\'Сгенерировать новый пароль? Текущий пароль будет перезаписан и не может быть восстановлен.\');"';
                break;
            case 'edit':
                $bsText = 'Редактировать';
                $btnContent = '<span class="material-symbols-outlined text-dark">edit</span>';
                $classes[] = 'btn-outline-gray';
                break;
            case 'delete':
                $bsText = 'Удалить';
                $btnContent = '<span class="material-symbols-outlined text-danger">close</span>';
                $classes[] = 'btn-outline-gray-secondary';
                $other = 'onclick="ajaxDelete(this, \'' . $action['id'] . '\');"';
                break;
            case 'person_add':
                $bsText = 'Поделиться с другим пользователем';
                $btnContent = '<span class="material-symbols-outlined text-dark">person_add</span>';
                $classes[] = 'btn-outline-gray';
                break;
            case 'url':
                $bsText = 'Перейти на URL';
                $btnContent = '<span class="material-symbols-outlined text-dark">captive_portal</span>';
                $classes[] = 'btn-outline-gray';
                break;
            default:
                continue 2;
        }
        foreach ($action['data'] ?? [] as $k => $v) {
            $other .= ' data-' . $k . '="' . $v . '" ';
        }
        $out .= '<' . $tag . ' ' . implode(' ', $attrs) . ' class="btn ' . implode(' ', $classes) . '"
            type="button"
            data-bs-toggle="popover" data-bs-trigger="hover"
            data-bs-placement="top"
            data-bs-content="' . $bsText . '" ' . $other . '>' . $btnContent . '</' . $tag . '>';
    }
    $out .= '</div>';
    return $out ?? '';
}

function getHiddenText($text, $needShow = true)
{
    $out = '<span class="hidden-text-container hidden">';
    $out .= '<span class="hidden-text">' . $text . '</span>';
    $out .= '<span class="hidden-text-fake">&#8727;&#8727;&#8727;&#8727;&#8727;&#8727;&#8727;&#8727;</span>';
    $out .= '<span class="hidden-text-copy"></span>';
    if ($needShow) {
        $out .= '<span class="hidden-text-show"></span>';
    }
    $out .= '</span>';
    return $out ?? '';
}

function getCopyTextIcon($text)
{
    $out = '<span class="hidden-text-container hidden">';
    $out .= '<span class="hidden-text">' . $text . '</span>';
    $out .= '<span class="hidden-text-copy"></span>';
    $out .= '</span>';
    return $out ?? '';
}
