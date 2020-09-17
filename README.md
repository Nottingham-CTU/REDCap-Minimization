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
* Specify a number of allocations to assign randomly before minimization is used.
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
  * **initial**: Whether this is one of the initial random allocations before minimization is used
  * **factor**: Which random factor has been used
    (null if not triggered or random factor not enabled)
    * S: Skip allocation (once)
    * C: Skip allocation (compounding)
    * R: Allocate randomly
  * **values**: Random numbers generated (between 0 and 100) which are compared with the random
    factor percentage value
  * **details**: Text description of the applied random factor
* **bogus_value**: The index of codes_full used for the fake randomization allocation


## Randomization algorithm

The algorithm used for minimization is as follows:

1. Get all records in the project, excluding any which are not randomized or where at least one
   of the stratification variables has a different value to the record which is to be randomized.
   The remaining records are the **strata**.
   * If there are no stratification variables, the strata is all previously randomized records.
   * The randomization will fail if any of the stratification variables for the record to be
     randomized are empty.
2. Determine the minimization **mode** (set of allocations and minimization variables) to use. If
   multiple minimization modes are not in use, the first (and only) mode is used.
   * If multiple minimization modes are in use, and the minimization mode variable for the record
     to be randomized is blank or does not match any of the mode values, then the randomization will
     fail.
3. Get the randomization allocation codes and ratios for the mode, and get the minimization variable
   values for the record to be randomized.
   * The randomization will fail if any of the minimization variables for the record to be
     randomized are empty.
4. For each allocation, set the minimization total to 0.
5. For each record in the strata, get the value for each minimization variable. Compare the value
   with the value in the record to be randomized. If the values match, increment by 1 the value of
   the minimization total which corresponds to the allocation of the record in the strata.
6. Adjust the minimization totals according to the ratios. This is done by multiplying each
   minimization total by the lowest common multiple of all of the ratios, then dividing by the
   ratio for the allocation.
   * The multiplication step ensures that minimization totals are still integers after division.
   * The division step reduces the minimization total, making it more likely that a record is
     randomized to that allocation. For example, an allocation with ratio 2 will be used twice as
     much as an allocation with ratio 1.
7. Perform the randomization. This is done by ordering the allocations by minimization total and
   selecting the allocation with the smallest total.
   * A separate **proportional list** of allocation codes (in which each code appears ratio times)
     is also generated, which is used for some of the random factors and the fake allocation.
8. Apply the random factor, if applicable. The random factor will be applied for the specified
   percentage of randomizations.
   * If an initial number of records to allocate randomly is specified, and the number of records
     randomized so far (including the current one) is less than or equal to the initial number, the
     randomized allocation is discarded and an allocation is picked from the proportional list at
     random instead.
     * The following random factors only apply after the initial records which are allocated
       randomly.
   * If the **skip allocation (once)** factor is enabled, for the specified percentage of
     randomizations, the allocation with the lowest minimization total is disregarded and the
     allocation with the next lowest minimization total is used instead.
   * If the **skip allocation (compounding)** factor is enabled, this will perform multiple rounds,
     successively disregarding the allocation with the next lowest minimization total for the
     specified percentage of randomizations. So if the percentage is 20%, the allocation with the
     lowest minimization total is disregarded 20% of the time, and the allocation with the second
     lowest minimization total is also disregarded 4% of the time (as 4 is 20% of 20). This process
     will stop if only one allocation is remaining.
   * If the **allocate randomly** factor is enabled, this will discard the randomized allocation for
     the specified percentage of randomizations and an allocation is picked from the proportional
     list at random instead.

The fake allocation (if a field for this has been specified) is picked at random from the
proportional list. This process is separate from the real randomization allocation.

