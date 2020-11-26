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
  automatically upon submission of a form (when form status is complete).
* Apply a random factor so that the minimized allocation is not chosen every time.
* Specify a number of allocations to assign randomly before minimization is used.
* Store diagnostic output from the stratification and minimization in a field on the project.

Please note that this module is not intended to be used with repeating instruments or events. All
fields used for randomization allocations, minimization variables and stratification variables
should not be on a repeating instance or event.


## Project-level configuration options

### Randomization event
The arm and event on the project where the randomization field is located. Also used for the
randomization date/time field, fake randomization field, and diagnostic output field.

### Randomization field
The field where the randomization allocation is stored. Either a standard text field or a multiple
choice field can be used for this. If using a multiple choice field, ensure that **all** of the
randomization allocations from all of the minimization modes are present in the choices.

This field will not be editable by users (except to perform a randomization either by clicking the
randomize button or submitting the form). The randomization date/time, fake randomization, and
diagnostic output fields will however be editable by users unless you set a @READONLY or @HIDDEN
action tag on them.

### Field to store the date/time of randomization
The field where the date/time of randomization will be stored. Like the randomization, fake
randomization, and diagnostic output fields, a value will only be saved for this field if
randomization is successful. Use a text field for this, optionally with a date or datetime
validation type (which will display the date in the specified format).

### Timezone for randomization date/time
The timezone used for the randomization date/time. The following options are currently supported:
* UTC
* Server timezone

### Field to store a fake randomization allocation
A value determined separately from the randomization allocation will be stored here. This can be
useful for blinded data extracts. It is unlikely to be useful (and may be confusing) to site
researchers, so the @HIDDEN action tag should be used to hide the field. The field type should be
the same as on the randomization field.

### Field to store diagnostic output for the randomization
This field stores information that can be useful for verifying or debugging your randomization
configuration. It is unlikely to be useful (and may be confusing) to site researchers, so the
@HIDDEN action tag should be used to hide the field. A notes box field type should be used for this.

### Stratification variables
The stratification variables split the records into stratas, where the *strata* is the set of
records for which all the stratification variables have the same values as on the record being
randomized. Randomization is performed with regards to the strata only. The event and field must be
specified for each stratification variable. There is no limit to the number of stratification
variables, but using a large number should be avoided.

### Use multiple minimization modes
If enabled, this allows a event and field to be defined, the value of which will determine which
minimization mode is used. If this is not enabled, only one minimization mode can be defined.
Multiple modes can be useful for studies where some of the allocations only apply to some of the
participants, or where some participants need to be minimized on different criteria.

### Minimization mode

#### Minimization mode value
If multiple minimization modes are enabled, this specifies the value of the minimization mode field
which will cause this minimization mode to be used. Each minimization mode must have a unique value.

#### Randomization allocation
Each randomization allocation must have a **code** (which is the raw value held in the dataset), a
**description** (which is the text displayed to users), and a **ratio** (which denotes how often an
allocation is to be used relative to the other allocations).

#### Minimization variable
Specify the event and field for each minimization variable. Each minimization mode must have at
least one minimization variable. The minimization algorithm will aim to place the new record in the
allocation for which there are the minimum matching values from existing records in the strata for
the minimization variables (adjusted for ratio).

### Automatically randomize on submission of form
Instead of presenting a *randomize* button in place of the randomization field which can be clicked
to perform randomization, the randomization can be performed automatically when a form is submitted.
It is possible to choose a form other than the one on which the randomization field appears.

#### Reset form status to incomplete on randomization failure
If this option is enabled, the submitted form triggering the randomization will have its status
reset to incomplete if the randomization fails. This can help give a visual indication of failed
randomizations on the record status dashboard and the record home page.

### Random factor
Optionally add a random factor, so the minimized allocation is not chosen every time. A description
of each random factor can be found below under *randomization algorithm*. If using a random factor,
the percentage of randomizations to which it is applied must be specified. This would typically be
a value greater than 0 and less than 50.

### Number of initial random allocations
If specified, the first records to be randomized, up to the number specified, will be allocated
randomly rather than by minimization.


## Diagnostic output

If a field is selected for diagnostic output, the following data will be written to the field as a
JSON object. For best results, the diagnostic output field should be of the **Notes Box** type.

* **num**: Randomization number (order in which the randomizations occurred)
* **stratify**: Whether stratification has been used (true/false)
* **strata_values**: Values of the stratification variables
* **strata_records**: The number of other records in the strata
* **minim_multi**: Whether multiple minimization modes have been used (true/false)
* **minim_mode**: Which minimization mode has been used
* **minim_mode_value**: Value of the minimization mode field
* **codes_full**: Expanded minimization codes list (each code listed ratio times)
* **minim_values**: Values of the minimization variables
* **minim_totals**: Minimization totals, with the following categories
  * **final**: Totals following adjustment for allocation ratio
  * **base**: Totals prior to adjustment for allocation ratio
  * **fields**: The per-field totals that sum to the base totals
  * **random**: Random numbers used for minimization when the minimization totals are equal
* **minim_alloc**: List of the randomization allocations, in minimized order
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
7. Generate a unique random number for each allocation.
8. Perform the randomization. This is done by ordering the allocations by minimization total and
   selecting the allocation with the smallest total.
   * If the minimization totals are equal, the random numbers are used instead.
   * A separate **proportional list** of allocation codes (in which each code appears ratio times)
     is also generated, which is used for some of the random factors and the fake allocation.
9. Apply the random factor, if applicable. The random factor will be applied for the specified
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

