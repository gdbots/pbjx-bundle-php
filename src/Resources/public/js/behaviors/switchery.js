/*
  Require libraries:
    - http://abpetkov.github.io/switchery/
*/

define(
[
  'jquery',
  'switchery',
  '../switchery-manager'
],
function ($, Switchery, SwitcheryManager) {
  'use strict';

  var SwitcheryComponent = function(options) {
    this.$el = $('#' + options.widgetId);
    this.options = options || {};

    this.initialize(options);
  };

  /**
   * @constructor
   * @param {Object} options
   */
  SwitcheryComponent.prototype.initialize = function(options) {
    var switchery = new Switchery(this.$el[0], options.widgetOptions);
    this.$el.data('switchery', switchery);

    SwitcheryManager.addInstance(options.widgetId, switchery);
  };

  return SwitcheryComponent;
});
