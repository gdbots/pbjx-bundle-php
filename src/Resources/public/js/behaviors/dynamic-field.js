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
    var self = this;

    self.$el.find('.js-dynamic-field-kind').on('change', function() {
      self.$el.find('.js-dynamic-field-option').hide();
      self.$el.find('.js-dynamic-field-option :input').attr('disabled', true)

      var $field = $(this).closest('.row').find('.js-dynamic-field-option :input[id$=_' + $(this).val() + ']');
      $field.off('change');
      $field.on('change', function() {
        self.$el.find('.js-dynamic-field-value')
          .val($(this).val());
      });
      $field.attr('disabled', false).change();
      $field.closest('.js-dynamic-field-option').show();
    })

    // run on load first time
    .change();
  };

  return DynamicFieldComponent;
});
