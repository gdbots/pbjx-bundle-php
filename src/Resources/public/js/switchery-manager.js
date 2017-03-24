export default () => {
  return {
    instances: [],

    addInstance(id, switchery) {
      this.instances[id] = switchery;
    },
    getInstance(id) {
      return this.instances[id] || null;
    }
  };
}
