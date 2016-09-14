/*
  Require libraries:
    - http://momentjs.com/
    - http://www.daterangepicker.com/
*/

define(
[
  'jquery',
  'moment',
  'daterangepicker'
],
function ($, moment) {
  'use strict';

  var DateRangePickerComponent = function(options) {
    this.$el = $('#' + options.widgetId);
    this.options = options || {};

    this.initialize(options);
  };

  /**
   * @constructor
   * @param {Object} options
   */
  DateRangePickerComponent.prototype.initialize = function(options) {
    _bindEvent.bind(this)();
  };

  /**
   * Binds widget instance to environment events.
   *
   * @protected
   */
  var _bindEvent = function() {
    var self = this;

    self.$el.change(function () {
      var $picker = $(this);
      if (!$picker.data('daterangepicker') || '' === $picker.val()) {
        return;
      }

      if (self.options.widgetType == 'date') {
        $picker.val(moment($picker.data('daterangepicker').startDate).format('YYYY-MM-DD'));
      } else {
        $picker.val(moment($picker.data('daterangepicker').startDate).format('YYYY-MM-DDTHH:mm:ss.SSSSSS') + 'Z');
      }
    });

    self.$el.click(function () {
      var $picker = $(this);
      if ($picker.data('daterangepicker')) {
        return;
      }

      $picker.daterangepicker(
        $.extend(true, self.options.widgetOptions, {
          'startDate': '' !== $picker.val() ? $picker.val() : moment()
        }),
        self.options.widgetCallback
      );

      $picker.click();
    });
  };

  return DateRangePickerComponent;
});
