# CHANGELOG for 2.x
This changelog references the relevant changes done in 2.x versions.


## v2.2.1
* Remove use of deprecated setPrivate method in RegisterHandlersPass.


## v2.2.0
__POSSIBLE BREAKING CHANGES__

* Remove use of mixin/message constants for fields and schema refs as it's too noisy and isn't enough of a help to warrant it.
* Merge `CommandBinder`, `EventBinder` and `RequestBinder` into one `MessageBinder`.
* Move `PermissionValidatorTrait` to root of project.


## v2.1.0
* Uses `"gdbots/pbjx": "^3.1"` and configures `DynamoDbScheduler` to use `gdbots_pbjx.event_dispatcher`.


## v2.0.0
__BREAKING CHANGES__

* Upgrade to support Symfony 5 and PHP 7.4.
* Uses `"gdbots/pbjx": "^3.0"`
* Make commands lazy by using the symfony static name.
* Removes all gearman configuration.
