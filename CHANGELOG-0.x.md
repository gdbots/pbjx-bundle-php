# CHANGELOG for 0.x
This changelog references the relevant changes done in 0.x versions.


## v0.2.0
__BREAKING CHANGES__

* Require `"symfony/console": "^4.0"` and `"symfony/framework-bundle": "^4.0"`.
* Remove all symfony form related functionality.  Our goal is to move all 
  form functionality to the client (react/angular/etc.) and use server side 
  validation with pbjx lifecycle events.
* Implement `x-pbjx-token` header validation using `PbjxToken`.
* Remove use of `ContainerAwareEventDispatcher` as it no longer exists in Symfony 4.
* Require `curie` attribute on all `pbjx.handler` service tags, e.g. `<tag name="pbjx.handler" curie="gdbots:pbjx:command:check-health"/>`.
* Change `AliasHandlersPass` to `RegisterHandlersPass` and use new `curie` attribute on `pbjx.handler`
  tag to automatically register handlers with closure for lazy loading.
* Register `Gdbots\Pbjx\Pbjx` interface as alias to service `pbjx` so autowiring works.


## v0.1.2
* Add support for Symfony 4.


## v0.1.1
* issue #7 :: BUG :: DI tag `pbjx.handler` doesn't process all tags.


## v0.1.0
* Initial version.
