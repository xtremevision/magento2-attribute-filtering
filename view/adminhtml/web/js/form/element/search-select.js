define([
    'jquery',
    'underscore',
    'Magento_Ui/js/form/element/select',
    'Magento_Ui/js/modal/prompt',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, _, Select, prompt, alert, $t) {
    'use strict';

    return Select.extend({
        defaults: {
            elementTmpl: 'Xtreme_AttributeFiltering/form/element/search-select',
            searchPlaceholder: 'Type to filter options',
            noMatchesMessage: 'No matches found',
            createOptionUrl: '',
            attributeCode: '',
            attributeFilteringType: 'dropdown',
            createOptionPromptTitle: 'Add New Option',
            createVisualSwatchPromptTitle: 'Choose Swatch Color',
            isDropdownOpen: false,
            searchTerm: '',
            filteredOptions: [],
            highlightedIndex: -1,
            creatingOption: false,
            blurDelay: 150,
            listens: {
                value: 'restoreSearchTerm',
                options: 'onOptionsChanged'
            }
        },

        initialize: function () {
            this._super();
            this.restoreSearchTerm();

            return this;
        },

        initObservable: function () {
            this._super()
                .observe([
                    'isDropdownOpen',
                    'searchTerm',
                    'filteredOptions',
                    'highlightedIndex',
                    'creatingOption'
                ]);

            return this;
        },

        onOptionsChanged: function () {
            this.applyFilter(false);
            this.restoreSearchTerm();
        },

        getSelectedLabel: function () {
            var value = this.value(),
                option;

            if (value === '' || value === null || typeof value === 'undefined') {
                return '';
            }

            option = this.getOption(value);

            return option ? option.label : '';
        },

        getCaptionOption: function () {
            if (!this.caption()) {
                return null;
            }

            return {
                value: '',
                label: this.caption(),
                isCaption: true
            };
        },

        getAvailableOptions: function () {
            var options = _.filter(this.options() || [], function (option) {
                    return option && !Array.isArray(option.value);
                }),
                captionOption = this.getCaptionOption();

            if (captionOption) {
                options.unshift(captionOption);
            }

            return options;
        },

        applyFilter: function (resetHighlight) {
            var term = (this.searchTerm() || '').toLowerCase(),
                filtered = _.filter(this.getAvailableOptions(), function (option) {
                    if (!term) {
                        return true;
                    }

                    return (option.label || '').toLowerCase().indexOf(term) !== -1;
                }),
                highlightedIndex = this.highlightedIndex();

            this.filteredOptions(filtered);

            if (resetHighlight === false) {
                if (!filtered.length) {
                    this.highlightedIndex(-1);
                } else if (highlightedIndex >= filtered.length) {
                    this.highlightedIndex(filtered.length - 1);
                }
            } else {
                this.highlightedIndex(filtered.length ? 0 : -1);
            }

            return filtered;
        },

        restoreSearchTerm: function () {
            if (!this.isDropdownOpen()) {
                this.searchTerm(this.getSelectedLabel());
            }

            this.applyFilter(false);
        },

        onSearchFocus: function () {
            if (this._blurTimeout) {
                clearTimeout(this._blurTimeout);
            }

            if (this.searchTerm() === this.getSelectedLabel()) {
                this.searchTerm('');
            }

            this.isDropdownOpen(true);
            this.applyFilter();
        },

        onSearchBlur: function () {
            this._blurTimeout = setTimeout(function () {
                this.isDropdownOpen(false);
                this.restoreSearchTerm();
            }.bind(this), this.blurDelay);
        },

        onSearchInput: function () {
            this.isDropdownOpen(true);
            this.applyFilter();
        },

        onSearchKeydown: function (data, event) {
            var filtered = this.filteredOptions(),
                highlightedIndex = this.highlightedIndex();

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    if (!this.isDropdownOpen()) {
                        this.onSearchFocus();
                    } else if (filtered.length) {
                        this.highlightedIndex(Math.min(highlightedIndex + 1, filtered.length - 1));
                    }
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    if (!this.isDropdownOpen()) {
                        this.onSearchFocus();
                    } else if (filtered.length) {
                        this.highlightedIndex(Math.max(highlightedIndex - 1, 0));
                    }
                    break;
                case 'Enter':
                    if (this.isDropdownOpen() && filtered[highlightedIndex]) {
                        event.preventDefault();
                        this.selectOption(filtered[highlightedIndex], event);
                    }
                    break;
                case 'Escape':
                    this.isDropdownOpen(false);
                    this.restoreSearchTerm();
                    break;
            }

            return true;
        },

        selectOption: function (option, event) {
            if (event) {
                event.preventDefault();
            }

            this.value(option.value);
            this.isDropdownOpen(false);
            this.restoreSearchTerm();

            return false;
        },

        highlightOption: function (option) {
            var index = this.filteredOptions().indexOf(option);

            this.highlightedIndex(index);
        },

        isOptionHighlighted: function (option) {
            return this.filteredOptions().indexOf(option) === this.highlightedIndex();
        },

        isOptionSelected: function (option) {
            return String(option.value) === String(this.value());
        },

        canCreateOption: function () {
            return !!this.createOptionUrl &&
                !!this.attributeCode &&
                !!$.trim(this.searchTerm()) &&
                !this.filteredOptions().length &&
                !this.creatingOption();
        },

        getCreateOptionLabel: function () {
            return $t('Add') + ' "' + $.trim(this.searchTerm()) + '"';
        },

        createOption: function () {
            var label = $.trim(this.searchTerm());

            if (!this.canCreateOption()) {
                return false;
            }

            if (this.attributeFilteringType === 'visual') {
                this.promptVisualSwatchValue(label);
                return false;
            }

            this.submitCreateOption(label, this.attributeFilteringType === 'text' ? label : '');

            return false;
        },

        promptVisualSwatchValue: function (label) {
            prompt({
                title: $t(this.createVisualSwatchPromptTitle),
                content: $t('Enter a hex color for the new swatch option.'),
                value: '#336699',
                validation: true,
                validationRules: ['required-entry'],
                actions: {
                    confirm: function (value) {
                        this.submitCreateOption(label, $.trim(value));
                    }.bind(this)
                }
            });
        },

        submitCreateOption: function (label, swatchValue) {
            var payload = {
                attribute_code: this.attributeCode,
                attribute_type: this.attributeFilteringType,
                label: label,
                swatch_value: swatchValue || '',
                data_scope: this.dataScope || '',
                field_index: this.index || '',
                form_key: window.FORM_KEY
            };

            this.creatingOption(true);
            $('body').trigger('processStart');

            $.ajax({
                url: this.createOptionUrl,
                type: 'POST',
                dataType: 'json',
                data: payload
            }).done(function (response) {
                if (!response || response.error || !response.option) {
                    this.showCreateOptionError(response && response.message ? response.message : $t('The option could not be created.'));
                    return;
                }

                this.appendCreatedOption(response.option);
                this.showCreateOptionSuccess(response);
            }.bind(this)).fail(function (xhr) {
                var message = $t('The option could not be created.');

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                this.showCreateOptionError(message);
            }.bind(this)).always(function () {
                this.creatingOption(false);
                $('body').trigger('processStop');
            }.bind(this));
        },

        appendCreatedOption: function (option) {
            var options = (this.options() || []).slice();

            options.push(option);
            this.setOptions(options);
            this.value(option.value);
            this.isDropdownOpen(false);
            this.restoreSearchTerm();
        },

        showCreateOptionError: function (message) {
            alert({
                content: message
            });
        },

        showCreateOptionSuccess: function (response) {
            var message = response && response.message ?
                response.message :
                $t('The option was added.');

            alert({
                title: $t('Option Added'),
                content: message
            });
        }
    });
});
