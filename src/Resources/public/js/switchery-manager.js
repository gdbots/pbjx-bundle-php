define(
[

],
function () {
  'use strict';

  return {
    instances: [],

    addInstance: function(id, switchery) {
      this.instances[id] = switchery;
    },
    getInstance: function(id) {
      return this.instances[id] || null;
    }
  };
});
