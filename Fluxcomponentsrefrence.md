this file has References for some flux components as input , button , modal, card
flux:button
Prop
Description
as	
The HTML tag to render the button as. Options: button (default), a, div.
href	
The URL to link to when the button is used as an anchor tag.
type	
The HTML type attribute of the button. Options: button (default), submit.
variant	
Visual style of the button. Options: outline, primary, filled, danger, ghost, subtle. Default: outline.
size	
Size of the button. Options: base (default), sm, xs.
icon	
Name of the icon to display at the start of the button.
icon:variant	
Visual style of the icon. Options: outline, solid, mini, micro. Default: micro.
icon:trailing	
Name of the icon to display at the end of the button.
square	
If true, makes the button square. (Useful for icon-only buttons.)
align	
Alignment of the button content. Options: start, center, end. Default: center.
inset	
Add negative margins to specific sides. Options: top, bottom, left, right, or any combination of the four.
loading	
If true, shows a loading spinner and disables the button when used with wire:click or type="submit". If false, the button will not show a loading spinner at all. Default: true.
tooltip	
Text to display in a tooltip when hovering over the button.
tooltip:position	
Position of the tooltip. Options: top, bottom, left, right. Default: top.
tooltip:kbd	
Text to display in a keyboard shortcut tooltip when hovering over the button.
kbd	
Text to display in a keyboard shortcut tooltip when hovering over the button.
CSS
Description
class	
Additional CSS classes applied to the button. Common use: w-full for full width.
Attribute
Description
data-flux-button	
Applied to the root element for styling and identification.
flux:button.group
A container component that groups multiple buttons together with shared borders.

Slot
Description
default	
The buttons to be grouped together.
Reference
flux:input
Prop
Description
wire:model	
Binds the input to a Livewire property. See the wire:model documentation for more information.
label	
Label text displayed above the input. When provided, wraps the input in a flux:field component with an adjacent flux:label component. See the field component.
description	
Help text displayed above the input. When provided alongside label, appears between the label and input within the flux:field wrapper. See the field component.
description:trailing	
Help text displayed below the input. When provided alongside label, appears below the label and input within the flux:field wrapper. See the field component.
placeholder	
Placeholder text displayed when the input is empty.
size	
Size of the input. Options: sm, xs.
variant	
Visual style variant. Options: filled. Default: outline.
disabled	
Prevents user interaction with the input.
readonly	
Makes the input read-only.
invalid	
Applies error styling to the input.
multiple	
For file inputs, allows selecting multiple files.
mask	
Input mask pattern using Alpine's mask plugin. Example: 99/99/9999.
mask:dynamic	
Dynamic input mask pattern using Alpine's mask plugin. Example: $money($input).
icon	
Name of the icon displayed at the start of the input.
icon:trailing	
Name of the icon displayed at the end of the input.
kbd	
Keyboard shortcut hint displayed at the end of the input.
clearable	
If true, displays a clear button when the input has content.
copyable	
If true, displays a copy button to copy the input's content (https only).
viewable	
For password inputs, displays a toggle to show/hide the password.
as	
Render the input as a different element. Options: button. Default: input.
input:class	
CSS classes applied directly to the input element instead of the wrapper.
Slot
Description
icon	
Custom content displayed at the start of the input (e.g., icons).
icon:leading	
Custom content displayed at the start of the input (e.g., icons).
icon:trailing	
Custom content displayed at the end of the input (e.g., buttons).
Attribute
Description
data-flux-input	
Applied to the root element for styling and identification.
flux:input.group
Slot
Description
default	
The input group content, typically containing an input and prefix/suffix elements.
flux:input.group.prefix
Slot
Description
default	
Content displayed before the input (e.g., icons, text, buttons).
flux:input.group.suffix
Slot
Description
default	
Content displayed after the input (e.g., icons, text, buttons).

flux:card
Slot
Description
default	
Content to display within the card. Can include headings, text, forms, buttons, and other components.
CSS
Description
class	
Additional CSS classes applied to the card. Common uses: space-y-6 for spacing between child elements, max-w-md for width control, p-0 to remove padding.
Attribute
Description
data-flux-card	
Applied to the root element for styling and identification.

Reference
flux:modal
Prop
Description
name	
Unique identifier for the modal. Required when using triggers.
flyout	
If true, the modal will open as a flyout.
variant	
Visual style of the modal. Options: default, floating, bare (legacy: flyout).
position	
For flyout modals, the direction they open from. Options: right (default), left, bottom.
scroll	
Scrolling behavior for long content. Options: body. When set to body, the entire viewport scrolls instead of clipping overflow.
dismissible	
If false, prevents closing the modal by clicking outside. Default: true.
closable	
If false, hides the close button. Default: true.
wire:model	
Optional Livewire property to bind the modal's open state to.
Event
Description
close	
Triggered when the modal is closed by any means.
cancel	
Triggered when the modal is closed by clicking outside or pressing escape.
Slot
Description
default	
The modal content.
Class
Description
w-*	
Common use: md:w-96 for width.
flux:modal.trigger
Prop
Description
name	
Name of the modal to trigger. Must match the modal's name.
shortcut	
Keyboard shortcut to open the modal (e.g., cmd.k).
Slot
Description
default	
The trigger element (e.g., button).
flux:modal.close
Slot
Description
default	
The close trigger element (e.g., button).
Flux::modal()
PHP method for controlling modals from Livewire components.

Parameter
Description
default|name	
Name of the modal to control.
Method
Description
close()	
Closes the modal.
Flux::modals()
PHP method for controlling all modals on the page.

Method
Description
close()	
Closes all modals on the page.
$flux.modal()
Alpine.js magic method for controlling modals.

Parameter
Description
default|name	
Name of the modal to control.
Method
Description
show()	
Shows the modal.
close()	
Closes the modal.