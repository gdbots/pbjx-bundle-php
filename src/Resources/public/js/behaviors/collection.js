define(
[
  'jquery'
],
function ($) {
  'use strict';

  var CollectionComponent = function(options) {
    this.$el = $('#' + options.widgetId);
    this.options = options || {};

    this.initialize(options);
  };

  /**
   * @constructor
   * @param {Object} options
   */
  CollectionComponent.prototype.initialize = function(options) {
    _bindEvent.bind(this)();
  };

  /**
   * Binds widget instance to environment events.
   *
   * @protected
   */
  var _bindEvent = function() {
    var self = this;

    self.$el.parent('.row-collection').on('click', '.js-btn-add-collection-item-btn', function(e) {
      e.preventDefault();

      if ($(this).attr('disabled')) {
        return;
      }

      var rowCountAdd = self.$el.data('row-count-add') || 1;
      var collectionInfo = _getCollectionInfo(self.$el);

      for (var i = 1; i <= rowCountAdd; i++) {
        var nextItemHtml = _getCollectionNextItemHtml(collectionInfo);
        collectionInfo.nextIndex++;
        self.$el.append(nextItemHtml)
          .trigger('content:changed')
          .data('last-index', collectionInfo.nextIndex);
      }

      self.$el.find('input.position-input').each(function(i, el) {
        $(el).val(i);
      });
    });

    self.$el.parent('.row-collection').on('click', '.js-btn-remove-collection-item-btn', function(e) {
      e.preventDefault();

      if ($(this).attr('disabled')) {
        return;
      }

      var closest = '*[data-content]';
      if ($(this).data('closest')) {
          closest = $(this).data('closest');
      }

      var item = $(this).closest(closest);
      item.trigger('content:remove')
        .remove();
    });
  };

  /**
   * Get collection prototype settings.
   *
   * @return {Object}
   *
   * @protected
   */
  var _getCollectionInfo = function($el) {
    var index = $el.data('last-index') || $el.children().length;
    var prototypeName = $el.attr('data-prototype-name') || '__name__';
    var html = $el.attr('data-prototype');

    return {
      nextIndex: index,
      prototypeHtml: html,
      prototypeName: prototypeName
    };
  };

  /**
   * Returns the collection item
   *
   * @param {Object} collectionInfo
   *
   * @return {String}
   *
   * @protected
   */
  var _getCollectionNextItemHtml = function(collectionInfo) {
    return collectionInfo.prototypeHtml.replace(new RegExp(collectionInfo.prototypeName, 'g'), collectionInfo.nextIndex);
  };


  return CollectionComponent;
});
