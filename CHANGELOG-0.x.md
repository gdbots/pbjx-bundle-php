# CHANGELOG for 0.x
This changelog references the relevant changes done in 0.x versions.


## v0.2.0
__BREAKING CHANGES__

* Remove all symfony form related functionality.  Our goal is to move all 
  form functionality to the client (react/angular/etc.) and use server side 
  validation with pbjx lifecycle events.
* Implementation `x-pbjx-token` header validation using `PbjxToken`.


## v0.1.2
* Add support for Symfony 4.


## v0.1.1
* issue #7 :: BUG :: DI tag 'pbjx.handler' doesn't process all tags.


## v0.1.0
* Initial version.
