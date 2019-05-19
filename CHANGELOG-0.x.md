# CHANGELOG for 0.x
This changelog references the relevant changes done in 0.x versions.


## v0.3.8
* In `MessageBinderTrait::bindIp` set field `ctx_ipv6` if it's a v6 ip address, else use `ctx_ip` field.


## v0.3.7
* Allow the client to set `expected_etag` on commands.


## v0.3.6
* Use `app_env` parameter if available instead of `kernel.environment`.


## v0.3.5
* Require `"gdbots/uri-template": "~0.2"`.
* Add Twig function `pbj_url` and `uri_template_expand`.


## v0.3.4
* Ensure permission check is bypassed when pbjx requests are executed through the twig extension.


## v0.3.3
* Use two colons for route config as single is deprecated since Symfony 4.1.


## v0.3.2
* Use `Throwable` interface in place of all `Exception` typehints.


## v0.3.1
* Add configuration and support for optional Pbjx Scheduler service.


## v0.3.0
__BREAKING CHANGES__

* Require `"gdbots/pbjx": "^2.1.1"`.
* Embrace Symfony autoconfigure by using the Pbjx marker interfaces to automatically tag
  services that use those interfaces.  This removes the need to define these in your app
  unless you have customized needs that autoconfigure/autowiring doesn't address.
* Add `x-pbjx-dry-run` header to pbjx endpoint to allow for a command/event to be received
  and acknowledged but not actually processed.
* `PbjxAwareControllerTrait` renamed to `PbjxControllerTrait` and the `getPbjx` method was removed.


## v0.2.1
* Fix bug with `RunGearmanConsumerCommand` requesting private logger service.


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
* Remove the `HandlerGuesser` class and service configurations.  This will be replaced
  in the future with auto tagging based on interfaces.


## v0.1.2
* Add support for Symfony 4.


## v0.1.1
* issue #7 :: BUG :: DI tag `pbjx.handler` doesn't process all tags.


## v0.1.0
* Initial version.
