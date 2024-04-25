# REDCap-Minimization
REDCap External Module: Perform minimization.

*Note: American spellings for minimization and randomization are used throughout for consistency
with REDCap. Support for British spellings is provided in this module using REDCap's
internationalization feature.*

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

### Logic which must be satisfied to allow randomization
If specified, this logic will be tested and randomization will only proceed if the logic evaluates
to true. If not specified, randomization will always be allowed when possible.

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
The randomization will only be performed if the form status has been set to complete. It is possible
to choose a form other than the one on which the randomization field appears.

#### Reset form status to incomplete on randomization failure
If this option is enabled, the submitted form triggering the randomization will have its status
reset to incomplete if the randomization fails. This can help give a visual indication of failed
randomizations on the record status dashboard and the record home page.

### Random factor
Optionally add a random factor, so the minimized allocation is not chosen every time. Diagrams of
each random factor are below and a description of each random factor can be found later in this
document under *randomization algorithm*. If using a random factor, the percentage of randomizations
to which it is applied must be specified. This would typically be a value greater than 0 and less
than 50.

<img src="data:image/png;base64,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">

### Number of initial random allocations
If specified, the first records to be randomized, up to the number specified, will be allocated
randomly rather than by minimization.

#### Count records within strata when performing initial random allocations
This setting determines how to count the existing records when determining whether the new record is
one of the initial records allocated randomly.

* **No strata** will count all records in the project, so the initial specified number of records
  *project-wide* are allocated randomly
* **Use randomization strata** will count only the records with matching stratification variables,
  so the initial specified number of records *in each strata* are allocated randomly
* **Use custom strata** will apply the per-strata approach, but with a secondary strata defined
  exclusively for this purpose

### Diagnostic download mode
Select whether headings in the CSV download are standard (includes event/arm prefixes on fields), or
always omit event/arm prefixes. This only affects longitudinal projects.


## Diagnostic output

If a field is selected for diagnostic output, the following data will be written to the field as a
JSON object. For best results, the diagnostic output field should be of the **Notes Box** type.

* **num**: Randomization number (order in which records were randomized)
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
  * **threshold**: The percentage (or initial number of random allocations) which determines whether
    minimization is used
  * **values**: Random numbers generated (between 0 and 100) which are compared with the random
    factor percentage value
  * **details**: Text description of the applied random factor
* **bogus_value**: The index of codes_full used for the fake randomization allocation

If diagnostic output is used, users with design rights (or module specific rights if enabled) can
download a CSV file of the diagnostic data for all records. The fields in the CSV file are as
follows:

* _(project record ID field)_
* _(randomization field)_
* _(randomization date/time field, if selected)_
* _(fake randomization allocation field, if selected)_
* **rando_num**: Randomization number (order in which records were randomized)
* **stratify**: Whether stratification has been used (1=true/0=false)
* _(stratification variables)_: If stratification has been used, the value of each stratification
  variable
* **strata_records**: If stratification has been used, the number of records in the strata
* _(minimization variables)_: The value of each minimization variable
* **minim_alloc_**<i>(n)</i>: The <i>n</i>th most minimized allocation
* **minim_total_**<i>(code)</i>: The minimization total for allocation <i>code</i>
* **minim_rtotal_**<i>(code)</i>: The random number for allocation <i>code</i> used for minimization
  when the minimization totals are equal
* **minim_initial**: Whether this is one of the initial random allocations before minimization is
  used (1=true/0=false)
* **minim_threshold**: The percentage (or initial number of random allocations) which determines
  whether minimization is used
* **minim_random_**<i>(n)</i>: The <i>n</i>th random number generated which is compared with the
  random factor percentage value
* **minim_random_details**: Text description of the applied random factor
* **minim_btotal_**<i>(code)</i>: The base minimization total for allocation <i>code</i>, prior to
  adjusting for the ratios
* **minim_ftotal_**<i>(code)</i>**_**<i>(field)</i>: The minimization total for allocation
  <i>code</i> for variable <i>field</i>
* **minim_max_diff**: The maximum difference between two minimization totals for a field



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
   * If the minimization totals are equal, the random numbers from the previous step are used
     instead.
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

