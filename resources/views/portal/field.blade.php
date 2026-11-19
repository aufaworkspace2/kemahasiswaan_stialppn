@php($value=old('answers.'.$field['key'],$application?->answers[$field['key']]??''))
<div class="{{ $field['type']==='textarea'?'md:col-span-2':'' }}"><label for="field_{{ $field['key'] }}">{{ $field['label'] }} @if($field['required']??false)<b>*</b>@endif</label>
@switch($field['type'])
@case('textarea')<textarea id="field_{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" rows="4" maxlength="{{ $field['max']??2000 }}" @required($field['required']??false)>{{ $value }}</textarea>@break
@case('select')<select id="field_{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" @required($field['required']??false)><option value="">Pilih salah satu</option>@foreach($field['options']??[] as $option)<option @selected($value===$option)>{{ $option }}</option>@endforeach</select>@break
@case('multiselect')<select id="field_{{ $field['key'] }}" name="answers[{{ $field['key'] }}][]" multiple @required($field['required']??false)>@foreach($field['options']??[] as $option)<option @selected(in_array($option,(array)$value,true))>{{ $option }}</option>@endforeach</select><small>Gunakan Ctrl atau Command untuk memilih lebih dari satu.</small>@break
@case('file')<input id="field_{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" type="file" accept=".pdf,.jpg,.jpeg,.png" @required(($field['required']??false) && !$application) data-max-file="5242880">@break
@default<input id="field_{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" type="{{ $field['type']==='number'?'number':($field['type']==='date'?'date':'text') }}" value="{{ is_array($value)?'':$value }}" @required($field['required']??false) @if($field['type']==='number') step="any" @else maxlength="{{ $field['max']??2000 }}" @endif>
@endswitch
@if(!empty($field['help']))<small>{{ $field['help'] }}</small>@endif @error('answers.'.$field['key'])<span class="field-error">{{ $message }}</span>@enderror</div>
