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


## Diagnostic output

If a field is selected for diagnostic output, the following data will be written to the field as a
JSON object. For best results, the diagnostic output field should be of the **Notes Box** type.

* **num**: Randomization number (order in which the randomizations occurred)
* **stratify**: Whether stratification has been used (true/false)
* **strata_values**: Values of the stratification variables
* **strata_records**: Other records in the strata
* **minim_multi**: Whether multiple minimization modes have been used (true/false)
* **minim_mode**: Which minimization mode has been used
* **minim_mode_value**: Value of the minimization mode field
* **codes_full**: Expanded minimization codes list (each code listed ratio times)
* **minim_values**: Values of the minimization variables
* **minim_totals**: Minimization totals, with the following categories
  * **final**: Totals following adjustment for allocation ratio
  * **base**: Totals prior to adjustment for allocation ratio
  * **fields**: The per-field totals that sum to the base totals
* **minim_random**: Details of an applied random factor (if no random factor, value is "none")
* **bogus_value**: The index of codes_full used for the fake randomization allocation
