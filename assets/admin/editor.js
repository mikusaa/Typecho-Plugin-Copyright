(function ($) {
    var config = window.TypechoCopyrightEditorConfig || {};
    var fields = $.extend(
        {
            mode: 'copyrightMode',
            author: 'copyrightAuthor',
            sourceUrl: 'copyrightSourceUrl',
            notice: 'copyrightNotice'
        },
        config.fields || {}
    );
    var legacyFields = $.extend(
        {
            switch: 'copyrightMode',
            author: 'copyrightAuthor',
            url: 'copyrightSourceUrl',
            notice: 'copyrightNotice'
        },
        config.legacyFields || {}
    );
    var fieldGroups = [
        {
            key: 'behavior',
            title: '显示策略',
            description: '控制当前内容是否显示版权声明，以及链接展示方式。',
            fields: ['mode', 'sourceUrl']
        },
        {
            key: 'content',
            title: '声明内容',
            description: '版权声明留空时回退到插件的全局默认值。',
            fields: ['notice']
        }
    ];
    var hiddenFieldKeys = ['author'];
    var modeLabels = {
        inherit: '跟随全局',
        enabled: '本篇启用',
        disabled: '本篇关闭'
    };

    function init() {
        var $customField = $('#custom-field');
        if (!$customField.length || $('#copyright-editor-fields').length) {
            return;
        }

        migrateLegacyFields($customField);

        var $panel = buildPanel($customField);
        if (!$panel || !$panel.length) {
            return;
        }

        initCollapsiblePanel($panel);
        initSegmentedModeControl($panel);
    }

    function migrateLegacyFields($customField) {
        var hasSwitch = !!findLegacyField($customField, 'switch');
        var hasAuthor = !!findLegacyField($customField, 'author');
        var hasUrl = !!findLegacyField($customField, 'url');
        var hasNotice = !!findLegacyField($customField, 'notice');

        if (!(hasSwitch || (hasNotice && (hasAuthor || hasUrl)))) {
            return;
        }

        $.each(
            [
                {
                    legacyName: 'switch',
                    targetName: legacyFields.switch,
                    transform: function (value) {
                        if (value === '1') {
                            return 'enabled';
                        }

                        if (value === '0') {
                            return 'disabled';
                        }

                        return '';
                    },
                    isEmpty: function (value) {
                        return !value || value === 'inherit';
                    }
                },
                {
                    legacyName: 'author',
                    targetName: legacyFields.author
                },
                {
                    legacyName: 'url',
                    targetName: legacyFields.url
                },
                {
                    legacyName: 'notice',
                    targetName: legacyFields.notice
                }
            ],
            function (_, migration) {
                var legacyField = findLegacyField($customField, migration.legacyName);
                var $target;
                var nextValue;

                if (!legacyField) {
                    return;
                }

                $target = findControl($customField, migration.targetName);
                if (!$target.length) {
                    return;
                }

                nextValue = migration.transform ? migration.transform(legacyField.value) : legacyField.value;
                if (!(migration.isEmpty ? migration.isEmpty(normalizeValue($target.val())) : normalizeValue($target.val()) === '')) {
                    nextValue = null;
                }

                if (nextValue !== null && nextValue !== '') {
                    $target.val(nextValue).trigger('change');
                }

                legacyField.row.remove();
            }
        );
    }

    function buildPanel($customField) {
        var collectedGroups = [];
        var movedCount = 0;
        var $hiddenFields = $();
        var $panel;
        var $grid;

        $.each(fieldGroups, function (_, group) {
            var $groupFields = $();

            $.each(group.fields, function (_, fieldKey) {
                var fieldName = fields[fieldKey];
                var $field = extractField($customField, fieldKey, fieldName);

                if ($field && $field.length) {
                    $groupFields = $groupFields.add($field);
                    movedCount++;
                }
            });

            if ($groupFields.length) {
                collectedGroups.push({
                    config: group,
                    fields: $groupFields
                });
            }
        });

        $.each(hiddenFieldKeys, function (_, fieldKey) {
            var fieldName = fields[fieldKey];
            var $field = extractField($customField, fieldKey, fieldName);

            if ($field && $field.length) {
                $field.addClass('copyright-editor-field--hidden').attr('aria-hidden', 'true');
                $hiddenFields = $hiddenFields.add($field);
            }
        });

        if (!movedCount && !$hiddenFields.length) {
            return null;
        }

        $panel = $(
            '<details id="copyright-editor-fields" class="copyright-editor-fields typecho-post-option">' +
            '  <summary class="copyright-editor-fields__summary">' +
            '    <span class="copyright-editor-fields__summary-main">' +
            '      <span class="copyright-editor-fields__title">版权声明设置</span>' +
            '      <span class="copyright-editor-fields__description">仅管理版权声明插件使用的字段，默认折叠显示。</span>' +
            '    </span>' +
            '    <span class="copyright-editor-fields__summary-meta">' +
            '      <span class="copyright-editor-fields__summary-state" data-panel-state-text>展开设置</span>' +
            '    </span>' +
            '  </summary>' +
            '  <div class="copyright-editor-fields__body">' +
            '    <div class="copyright-editor-fields__grid"></div>' +
            '  </div>' +
            '</details>'
        );
        $grid = $panel.find('.copyright-editor-fields__grid');

        $.each(collectedGroups, function (_, groupData) {
            var $group = $(
                '<section class="copyright-editor-fields__group copyright-editor-fields__group--full" data-group="' + groupData.config.key + '">' +
                '  <div class="copyright-editor-fields__group-header">' +
                '    <h4 class="copyright-editor-fields__group-title">' + groupData.config.title + '</h4>' +
                '    <p class="copyright-editor-fields__group-description">' + groupData.config.description + '</p>' +
                '  </div>' +
                '  <div class="copyright-editor-fields__group-body"></div>' +
                '</section>'
            );

            $group.find('.copyright-editor-fields__group-body').append(groupData.fields);
            $grid.append($group);
        });

        if ($hiddenFields.length) {
            $panel.append(
                $('<div class="copyright-editor-fields__hidden" aria-hidden="true"></div>').append($hiddenFields)
            );
        }

        $customField.before($panel);
        renameRawFieldSummary($customField);
        cleanupCustomField($customField);

        return $panel;
    }

    function initCollapsiblePanel($panel) {
        $panel.prop('open', false);

        syncPanelStateText($panel);

        $panel.on('click', '.copyright-editor-fields__summary', function () {
            window.setTimeout(function () {
                syncPanelStateText($panel);
            }, 0);
        });

        $panel.on('toggle', function () {
            syncPanelStateText($panel);
        });
    }

    function syncPanelStateText($panel) {
        $panel
            .find('[data-panel-state-text]')
            .text($panel.prop('open') ? '收起设置' : '展开设置');
    }

    function extractField($customField, fieldKey, fieldName) {
        var $control = findControl($customField, fieldName);
        var $row;
        var $labelSource;
        var $valueSource;
        var $cells;
        var $field;
        var $label;
        var $controlWrap;

        if (!$control.length) {
            return null;
        }

        $row = $control.closest('li.field, tr');
        if (!$row.length || $row.data('copyrightFieldMoved')) {
            return null;
        }

        if ($row.is('li')) {
            $labelSource = $row.children('.field-name').first();
            $valueSource = $row.children('.field-value').first();
        } else {
            $cells = $row.children('td');
            $labelSource = $cells.eq(0);
            $valueSource = $cells.eq(1);
        }

        if (!$labelSource.length || !$valueSource.length) {
            return null;
        }

        $field = $('<section class="copyright-editor-field" data-copyright-field="' + fieldKey + '"></section>');
        $label = $('<div class="copyright-editor-field__label"></div>');
        $controlWrap = $('<div class="copyright-editor-field__control"></div>');

        $label.append($labelSource.contents());
        $controlWrap.append(unwrapFieldValue($valueSource).contents());
        $field.append($label).append($controlWrap);

        compactFieldDescription($field);
        $row.data('copyrightFieldMoved', true).remove();

        return $field;
    }

    function unwrapFieldValue($valueSource) {
        if (
            $valueSource.children().length === 1 &&
            $valueSource.children().eq(0).is('div') &&
            !$valueSource.children().eq(0).attr('class')
        ) {
            return $valueSource.children().eq(0);
        }

        return $valueSource;
    }

    function compactFieldDescription($field) {
        var $controlWrap = $field.find('.copyright-editor-field__control').first();
        var $description = $controlWrap.find('.description').first();
        var descriptionText;
        var $textControl;

        if (!$description.length) {
            return;
        }

        descriptionText = normalizeValue($description.text());
        if (!descriptionText) {
            $description.remove();
            return;
        }

        $textControl = $controlWrap.find('textarea, input[type="text"], input[type="url"], input.text').first();
        if ($textControl.length && !$textControl.attr('placeholder')) {
            $textControl.attr('placeholder', descriptionText);
        }

        $field
            .find('.copyright-editor-field__label')
            .append($('<p class="copyright-editor-field__meta"></p>').text(descriptionText));
        $description.remove();
    }

    function initSegmentedModeControl($scope) {
        var $field = $scope.find('[data-copyright-field="mode"]').first();
        var $select = $field.find('select').first();
        var $segmented;

        if (!$field.length || !$select.length || $field.data('copyrightSegmentedReady')) {
            return;
        }

        $segmented = $('<div class="copyright-segmented-control" role="radiogroup" aria-label="显示策略"></div>');
        $segmented.css('--copyright-segment-count', String($select.find('option').length));

        $select.find('option').each(function () {
            var $option = $(this);
            var value = String($option.attr('value'));
            var label = Object.prototype.hasOwnProperty.call(modeLabels, value) ? modeLabels[value] : normalizeValue($option.text());

            $segmented.append(
                $('<button type="button" class="copyright-segmented-control__item" role="radio"></button>')
                    .attr('data-value', value)
                    .text(label)
            );
        });

        $select.after($segmented);
        $field.addClass('copyright-editor-field--segmented').data('copyrightSegmentedReady', true);

        function sync() {
            var currentValue = String($select.val());
            var isDisabled = $select.prop('disabled');

            $segmented.find('.copyright-segmented-control__item').each(function () {
                var $button = $(this);
                var isActive = $button.attr('data-value') === currentValue;

                $button.toggleClass('is-active', isActive);
                $button.attr('aria-checked', isActive ? 'true' : 'false');
                $button.attr('tabindex', isActive ? '0' : '-1');
                $button.prop('disabled', isDisabled);
            });
        }

        $segmented.on('click', '.copyright-segmented-control__item', function () {
            if ($select.prop('disabled')) {
                return;
            }

            $select.val($(this).attr('data-value')).trigger('change');
        });

        $segmented.on('keydown', '.copyright-segmented-control__item', function (event) {
            var keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
            var $buttons;
            var currentIndex;
            var nextIndex;

            if ($.inArray(event.key, keys) === -1) {
                return;
            }

            event.preventDefault();
            $buttons = $segmented.find('.copyright-segmented-control__item:not(:disabled)');
            currentIndex = $buttons.index(this);
            nextIndex = currentIndex;

            if (!$buttons.length) {
                return;
            }

            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = currentIndex <= 0 ? $buttons.length - 1 : currentIndex - 1;
            } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = currentIndex >= $buttons.length - 1 ? 0 : currentIndex + 1;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = $buttons.length - 1;
            }

            $buttons.eq(nextIndex).focus().trigger('click');
        });

        $select.on('change.copyrightSegmentedControl', sync);
        sync();
    }

    function cleanupCustomField($customField) {
        var $list = $customField.children('.fields');
        var $table = $customField.children('table.typecho-list-table');

        if ($list.length && !$list.children().length) {
            $list.remove();
        }

        if ($table.length && !$table.find('tr').length) {
            $table.remove();
        }
    }

    function renameRawFieldSummary($customField) {
        var $summary = $customField.children('summary').first();

        if ($summary.length) {
            $summary.text('其他自定义字段');
        }
    }

    function findLegacyField($customField, legacyName) {
        var $nameInput = $customField.find('input[name="fieldNames[]"]').filter(function () {
            return normalizeValue($(this).val()) === legacyName;
        }).first();
        var $row;
        var $valueInput;

        if (!$nameInput.length) {
            return null;
        }

        $row = $nameInput.closest('li.field, tr');
        $valueInput = $row.find('[name="fieldValues[]"]').first();

        return {
            row: $row,
            value: normalizeValue($valueInput.val())
        };
    }

    function findControl($scope, fieldName) {
        return $scope.find('[name="fields[' + fieldName + ']"], [name="' + fieldName + '"]').first();
    }

    function normalizeValue(value) {
        return $.trim(String(value || ''));
    }

    $(init);
})(window.jQuery);
