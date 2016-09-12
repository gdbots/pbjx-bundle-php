define(
[
  'jquery'
],
function ($) {
  'use strict';

  var DynamicFieldComponent = function(options) {
    this.$el = $('[data-dynamic-field=' + options.widgetId + ']');
    this.options = options || {};

    this.initialize(options);
  };

  /**
   * @constructor
   * @param {Object} options
   */
  DynamicFieldComponent.prototype.initialize = function(options) {
    _bindEvent.bind(this)();
  };

  /**
   * Binds widget instance to environment events.
   *
   * @protected
   */
  var _bindEvent = function() {
    this.$el.find('.js-dynamic-field-kind').on('change', function() {
      $(this).closest('.row').find('.js-dynamic-field-value-options :input:not(.js-dynamic-field-value)').hide();

      var $field = $(this).closest('.row').find('.js-dynamic-field-value-options :input[id$=_' + $(this).val() + ']');
      $field.off('change');
      $field.on('change', function() {
        $(this).closest('.js-dynamic-field-value-options')
          .find('.js-dynamic-field-value')
          .val($(this).val());
      });
      $field.show().change();
    })

    // run on load first time
    .change();
  };

  return DynamicFieldComponent;
});
