# REDCap-Minimization
REDCap External Module: Perform minimization.

*Note: American spellings for minimization and randomization are used throughout for consistency
with REDCap.*

This module provides the following functionality:

* Assign a randomization allocation to a field on the project.
* Assign a *fake* randomization allocation to a field on the project.
* Stratify on any number of variables (stratification variables can be in different events).
* Optionally use multiple *minimization modes* (sets of allocations and variables), determined by
  the value of a field.
* Define any number of randomization allocations and minimization variables in each minimization
  mode (minimization variables can be in different events).
* Manually randomize by clicking a button (in the place of the randomization field), or
  automatically upon submission of a form.
* Apply a random factor so that the minimized allocation is not chosen every time.
* Store diagnostic output from the stratification and minimization in a field on the project.

Please note that this module is not intended to be used with repeating instruments or events. All
fields used for randomization allocations, minimization variables and stratification variables
should not be on a repeating instance or event.
