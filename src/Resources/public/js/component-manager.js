define([
  'jquery',
], function($) {
  'use strict';

  function ComponentManager($el) {
    this.$el = $el;
    this.components = {};

    this.init();
  }

  ComponentManager.prototype.init = function(options) {
    var promises = [];
    var elements = [];
    var modules = [];

    _analyseDom.bind(this)(elements, modules);

    elements.forEach(function(element) {
      promises.push(_initComponent.bind(this)(element, options));
    }.bind(this));

    // optimize load time - preload components in separate layouts
    require(modules, undefined);
    return $.when.apply($, promises).then(function() {
      return arguments;
    });
  };

  /**
   * Collect all elements that have components declaration.
   *
   * @param {Array.<jQuery>} elements
   * @param {Array.<string>} modules
   *
   * @protected
   */
  var _analyseDom = function(elements, modules) {
    var self = this;
    var el = self.$el[0];

    self.$el.find('[data-page-component-module]').each(function() {
      var $elem = $(this);

      // optimize load time - push components to preload queue
      modules.push($elem.data('page-component-module'));

      // collects container elements
      elements.push($elem);

      _bindChangesEvents.bind(self)($elem);
    });
  };

  /**
   * Subscribes the view to content changes
   *  - on 'content:changed' event -- updates layout
   *  - on 'content:remove' event -- removes related components (if they are left undisposed)
   *
   * @protected
   */
  var _bindChangesEvents = function($elem) {
    var self = this;

    // if the container catches content changed event -- updates its layout
    $elem.on('content:changed', function(e) {
      if (e.isDefaultPrevented()) {
        return;
      }
      e.preventDefault();
      self.init();
    });

    // if the container catches content remove event -- disposes related components
    $elem.on('content:remove', function(e) {
      if (e.isDefaultPrevented()) {
        return;
      }
      e.preventDefault();
      $(e.target).find('[data-bound-component]').each(function() {
        var component = self.findComponent(this);
        if (component) {
          component.remove();
        }
      });
    });
  };

  /**
   * Read component's data attributes from the DOM element.
   *
   * @param {jQuery} $elem
   * @protected
   */
  var _readData = function($elem) {
    var data = {
      module: $elem.data('page-component-module'),
      options: $elem.data('page-component-options') || {}
    };

    if (data.options._sourceElement) {
      data.options._sourceElement = $(data.options._sourceElement);
    } else {
      data.options._sourceElement = $elem;
    }

    var name = $elem.data('page-component-name') || $elem.attr('data-fid');
    if (name) {
      data.options.name = name;
    }
    return data;
  };

  /**
   * Cleanup trace of data attributes in the DOM element.
   *
   * @param {jQuery} $elem
   *
   * @protected
   */
  var _cleanupData = function($elem) {
    $elem
      .removeAttr('data-page-component-module')
      .removeAttr('data-page-component-options');
  };

  /**
   * Initializes component for the element.
   *
   * @param {jQuery} $elem
   * @param {Object|null} options
   *
   * @returns {Promise}
   *
   * @protected
   */
  var _initComponent = function($elem, options) {
    var data = _readData.bind(this)($elem);
    _cleanupData.bind(this)($elem);

    // mark elem
    $elem.attr('data-bound-component', data.module);

    var initDeferred = $.Deferred();

    if (!data.options._sourceElement.get(0)) {
      var message = 'Cannot resolve _sourceElement by selector "' +
        data.options._sourceElement.selector + '"';
      _handleError.bind(this)(message, new Error(message));
      initDeferred.resolve();
    }

    var componentOptions = $.extend(true, {}, options || {}, data.options);
    require(
      [data.module],
      $.proxy(_onComponentLoaded, this, initDeferred, componentOptions),
      $.proxy(_onRequireJsError, this, initDeferred)
    );

    return initDeferred.promise();
  };

  /**
   * Handles component load success:
   *  - initializes the component
   *  - add the component to registry
   *
   * @param {jQuery.Deferred} initDeferred
   * @param {Object} options
   * @param {Function} Component
   *
   * @protected
   */
  var _onComponentLoaded = function(initDeferred, options, Component) {
    if (this.removed) {
      initDeferred.resolve();
      return;
    }

    var $elem = options._sourceElement;
    var name = options.name;

    if (name && this.components.hasOwnProperty(name)) {
      var message = 'Component with the name "' + name + '" is already registered in the layout';
      _handleError.bind(this)(message, new Error(message));

      // prevent interface from blocking by loader
      initDeferred.resolve();
      return;
    }

    var component = new Component(options);
    initDeferred.resolve(component);
  };

  /**
   * Handles component load fail.
   *
   * @param {jQuery.Deferred} initDeferred
   * @param {Error} error
   *
   * @protected
   */
  var _onRequireJsError = function(initDeferred, error) {
    var message = 'Cannot load module "' + error.requireModules[0] + '"';
    _handleError.bind(this)(message, error);
    // prevent interface from blocking by loader
    initDeferred.resolve();
  };

  /**
   * Error handler:
   *  - in production mode shows user friendly message
   *  - in dev mode output in console expanded stack trace and throws the error
   *
   * @param {string} message
   * @param {Error} error
   *
   * @protected
   */
  var _handleError = function(message, error) {
    var console = window.console;
    if (console && console.error) {
      console.error(message);
    } else {
      throw error;
    }
  };

  /**
   * Getter for components.
   *
   * @param {string} name
   */
  ComponentManager.prototype.get = function(name) {
    if (name in this.components) {
      return this.components[name].component;
    } else {
      return null;
    }
  };

  /**
   * Getter/setter for components.
   *
   * @param {string} name
   * @param {BaseComponent} component to set
   * @param {HTMLElement} el
   */
  ComponentManager.prototype.add = function(name, component, el) {
    if (this.removed) {
      // in case the manager already removed -- remove passed component as well
      component.remove();
      return;
    }
    this.remove(name);
    this.components[name] = {
      component: component,
      el: el
    };
    return component;
  };

  /**
   * Removes the component.
   *
   * @param {string} name component name to remove
   */
  ComponentManager.prototype.remove = function(name) {
    var item = this.components[name];
    delete this.components[name];
    if (item) {
      item.component.remove();
    }
  };

  /**
   * Destroys all linked components.
   */
  ComponentManager.prototype.removeAll = function() {
    for (var name in this.components) {
      this.remove(this.components[name]);
    }
  };

  /**
   * Removes component manager.
   */
  ComponentManager.prototype.remove = function() {
    this.removeAll();
    delete this.$el;
    this.removed = true;
    return typeof Object.freeze === 'function' ? Object.freeze(this) : void 0;
  };

  /**
   * Find component related to the element.
   *
   * @param {HTMLElement} el
   *
   * @returns {BaseComponent}
   */
  ComponentManager.prototype.findComponent = function(el) {
    var found = false;
    for (var name in this.components) {
      var item = this.components[name];
      if (item.el === el) {
        found = item;
      }
    }
    if (found) {
      return found.component;
    }
  };

  /**
   * Applies callback function to all component in the collection.
   *
   * @param {Function} callback
   * @param {Object} context
   */
  ComponentManager.prototype.forEachComponent = function(callback, context) {
    for (var name in this.components) {
      callback.apply(context, [this.components[name].component]);
    }
  };

  return ComponentManager;
});
