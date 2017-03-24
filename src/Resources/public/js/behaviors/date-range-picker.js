/*
  Require libraries:
    - http://momentjs.com/
    - http://www.daterangepicker.com/
*/

import $ from 'jquery';
import moment from 'moment';
import 'daterangepicker';

class DateRangePickerComponent {
  constructor(options) {
    this.$el = $(`#${options.widgetId}`);
    this.options = options || {};

    this.initialize(options);
  }

  /**
   * @constructor
   * @param {Object} options
   */
  initialize(options) {
    bindEvent.bind(this)();
  }
}

/**
 * Binds widget instance to environment events.
 *
 * @protected
 */
function bindEvent() {
  const self = this;

  self.$el.on('change', e => {
    const $picker = $(e.target);
    if (!$picker.data('daterangepicker') || '' === $picker.val()) {
      return;
    }

    if (self.options.widgetType == 'date') {
      $picker.val(moment($picker.data('daterangepicker').startDate).format('YYYY-MM-DD'));
    } else {
      $picker.val(`${moment($picker.data('daterangepicker').startDate).utc().format('YYYY-MM-DDTHH:mm:ss.SSSSSS')}Z`);
    }
  });

  self.$el.on('click', e => {
    const $picker = $(e.target);
    if ($picker.data('daterangepicker')) {
      return;
    }

    let startDate = moment().seconds(0);
    const minutes = startDate.minutes();
    if (minutes < 60) startDate.minutes(45);
    if (minutes < 45) startDate.minutes(30);
    if (minutes < 30) startDate.minutes(15);
    if (minutes < 15) startDate.minutes(0);

    if ('' !== $picker.val()) {
      startDate = self.options.widgetType == 'date'
        ? $picker.val()
        : moment.utc($picker.val()).local()
    }

    $picker.daterangepicker(
      $.extend(true, self.options.widgetOptions, {
        'startDate': startDate
      }),
      self.options.widgetCallback
    );

    $picker.click();
  });
};

export default DateRangePickerComponent;
