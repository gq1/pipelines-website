<?php

$cache_dir = dirname(dirname(__FILE__)).'/api_cache/json_launch';
$post_content_type = 'json_schema_launcher';
$post_keys = ['version', 'schema', 'nxf_flags', 'input_params', 'status'];
require_once('../includes/json_schema.php');

$is_cli_waiting = $cache['version'] !== 'web_builder';

// Save form output
$error_msgs = array();
if(isset($_POST['post_content']) && $_POST['post_content'] == "json_schema_launcher_webform"){
    $error_msgs = save_launcher_form();
}
function save_launcher_form(){
    // global vars
    global $cache_dir;
    global $cache;
    // Check cache ID
    if(!isset($_POST['cache_id'])){
        return ["No cache ID supplied"];
    }
    $id_check = validate_cache_id($_POST['cache_id']);
    if(!isset($id_check['status'])){
        return ["Problem loading cache: <pre>".$id_check.'</pre>'];
    }
    if($id_check['status'] == 'error'){
        return ["Problem loading cache: ".$id_check['message']];
    }
    // Load cache
    $cache_id = $_POST['cache_id'];
    $cache_fn = $cache_dir.'/'.$cache_id.'.json';
    if(!file_exists($cache_fn)) {
        return ["Cache file not found: <code>".$cache_fn.'</code>'];
    }
    $cache = json_decode(file_get_contents($cache_fn), true);
    if(!isset($cache['schema'])){
        return ["Cache had no schema: <code>".$cache_fn.'</code>'];
    }
    $cache['schema'] = json_decode($cache['schema'], true);
    if(!isset($cache['schema']['properties']) || count($cache['schema']['properties']) == 0){
        return ["Cache schema was empty: <code>".$cache_fn.'</code><pre>'.print_r($cache['schema'], true).'</pre>'];
    }

    // Overwrite some keys (not schema)
    $cache['version'] = 'web_builder';
    $cache['status'] = 'launch_params_complete';
    $cache['nxf_flags'] = array();
    $cache['input_params'] = array();

    // Loop through POST vars and set params
    foreach ($_POST as $k => $v){
        if(substr($k,0,7) == 'params_'){
            $cache['input_params'][substr($k,7)] = $v;
        }
        if(substr($k,0,9) == 'nxf_flag_'){
            $cache['nxf_flags'][substr($k,9)] = $v;
        }
    }
    // Write to JSON file
    $cache['schema'] = json_encode($cache['schema']);
    $cache['nxf_flags'] = json_encode($cache['nxf_flags']);
    $cache['input_params'] = json_encode($cache['input_params']);
    $cache_json = json_encode($cache, JSON_PRETTY_PRINT)."\n";
    file_put_contents($cache_fn, $cache_json);
    // Redirect to web URL
    header('Location: '.$self_url.'?id='.$cache_id);
    exit;
}

// Markdown parsing libraries
require_once('../includes/libraries/parsedown/Parsedown.php');
require_once('../includes/libraries/parsedown-extra/ParsedownExtra.php');
$pd = new ParsedownExtra();

function parse_md($text){
    global $pd;
    // Remove whitespace on lines that are only whitespace
    $text = preg_replace('/^\s*$/m', '', $text);
    // Remove global text indentation
    $indents = array();
    foreach(explode("\n",$text) as $l){
        if(strlen($l) > 0){
            $indents[] = strlen($l) - strlen(ltrim($l));
        }
    }
    if(min($indents) > 0){
        $text = preg_replace('/^\s{'.min($indents).'}/m', '', $text);
    }
    return $pd->text($text);
}

function build_form_param($param_id, $param, $is_required){

    $dash_param_id = substr($param_id, 0, 1) == '-' ? $param_id : '--'.$param_id;
    $form_param_name = 'params_'.$param_id;
    if(substr($param_id,0,1) == '-'){
        $form_param_name = 'nxf_flag_'.$param_id;
    }

    // Hidden
    $hide_class = '';
    if(isset($param['hidden']) && (strtolower($param['hidden']) == 'true' || $param['hidden'] === true)){
        $hide_class = 'is_hidden';
    }

    // Icon
    $fa_icon = '';
    if(isset($param['fa_icon'])){
        $fa_icon = '<i class="'.$param['fa_icon'].' fa-fw mr-3"></i>';
    }

    // Description
    $description = '';
    if(isset($param['description'])){
        $description = '<small class="form-text">'.$param['description'].'</small>';
    }

    // Help text
    $help_text_btn = '';
    $help_text = '';
    if(isset($param['help_text']) && strlen(trim($param['help_text'])) > 0){
        $help_text_btn = '<div class="input-group-append" title="Show help text" data-toggle="tooltip">
            <button class="btn input-group-btn" type="button" data-toggle="collapse" href="#help-text-'.$param_id.'" aria-expanded="false">
                <i class="fas fa-question-circle"></i>
            </button>
        </div>';
        $help_text = '<div class="collapse" id="help-text-'.$param_id.'">
            <div class="card card-body small text-muted launcher-help-text">
                '.parse_md($param['help_text']).'
            </div>
        </div>';
    }

    // Schema default value
    $placeholder = '';
    $value = '';
    if(isset($param['default'])){
        $placeholder = 'placeholder="'.$param['default'].'"';
        $value = $param['default'];
    }
    // Supplied value
    if(isset($cache['input_params'][$param_id])){
        $value = $cache['input_params'][$param_id];
    }

    // Required
    $required = '';
    $required_asterisk = '';
    $validation_text = '';
    if($is_required){
        $required = 'required';
        $required_asterisk = '<sup class="text-danger ml-2" title="Required" data-toggle="tooltip">*</sup>';
        $validation_text = '<div class="invalid-feedback">This parameter is required</div>';
    }

    // Text, number, integer, range input
    $input_type = 'text';
    $step = '';
    $minimum = '';
    $maximum = '';
    $pattern = '';
    if($param['type'] == 'number' || $param['type'] == 'integer'){
        $input_type = 'number';
    }
    if($param['type'] == 'range'){
        $input_type = 'range';
    }
    if($param['type'] == 'integer'){
        $step = 'step="1"';
        $pattern = 'pattern="\d+"';
        $validation_text = '<div class="invalid-feedback">Must be an integer</div>';
    }
    if(array_key_exists('minimum', $param) && strlen($param['minimum']) > 0){
        $minimum = 'min="'.$param['minimum'].'"';
    }
    if(array_key_exists('maximum', $param) && strlen($param['maximum']) > 0){
        $maximum = 'max="'.$param['maximum'].'"';
    }
    if(array_key_exists('pattern', $param) && strlen($param['pattern']) > 0){
        $pattern = 'pattern="'.$param['pattern'].'"';
        $validation_text = '<div class="invalid-feedback">Must match pattern <code>'.$param['pattern'].'</code></div>';
    }
    $input_el = '<input type="'.$input_type.'" '.$step.' '.$minimum.' '.$maximum.' '.$pattern.' class="form-control text-monospace" id="'.$form_param_name.'" name="'.$form_param_name.'" '.$placeholder.' value="'.$value.'" '.$required.'>';

    // Boolean input
    if($param['type'] == 'boolean'){
        $input_el = '
        <div class="form-control pl-4">
            <div class="form-check form-check-inline mr-4">
                <input '.($value === true || strtolower($value) == 'true' ? 'checked' : '').' class="form-check-input" type="radio" id="'.$form_param_name.'_true" name="'.$form_param_name.'" '.$required.' value="true">
                <label class="form-check-label" for="'.$param_id.'_true">True</label>
            </div>
            <div class="form-check form-check-inline">
                <input '.($value === false || strtolower($value) == 'false' ? 'checked' : '').' class="form-check-input" type="radio" id="'.$form_param_name.'_false" name="'.$form_param_name.'" '.$required.' value="false">
                <label class="form-check-label" for="'.$param_id.'_false">False</label>
            </div>
        </div>';
    }

    // enum input
    if(array_key_exists('enum', $param) && count($param['enum']) > 0){
        $input_el = '<select class="custom-select" id="'.$form_param_name.'" name="'.$form_param_name.'" '.$required.'>';
        foreach($param['enum'] as $option){
            $input_el .= '<option '.($value == $option ? 'selected' : '').' value="'.$option.'">'.$option.'</option>';
        }
        $input_el .= '</select>';
    }

    // Build HTML
    return '
    <div class="form-group param-form-group '.$hide_class.'" id="'.$param_id.'_group">
        <div class="input-group">
            <div class="input-group-prepend">
                <label class="input-group-text text-monospace" for="'.$param_id.'">'.$fa_icon.$dash_param_id.$required_asterisk.'</label>
            </div>
            '.$input_el.$help_text_btn.$validation_text.'
        </div>
        '.$description.$help_text.'
    </div>';
}

// Got this far without printing JSON - build web GUI
$title = 'Launch pipeline';
$subtitle = 'Configure workflow parameters for a pipeline run.';
if($cache) $import_schema_launcher = true;
include('../includes/header.php');

if(count($error_msgs) > 0){
    echo '<div class="alert alert-danger">'.implode('<br>', $error_msgs).'</div>';
}

if(!$cache){ ?>

<h3>Launch a pipeline</h3>

<p>You can run <code>nf-core launch</code> to submit any pipeline schema to this page and set the parameters required for launch.</p>

<?php } else { ?>

    <p class="lead">Params cache ID: <code id="params_cache_id"><?php echo $cache_id; ?></code> <small class="cache_expires_at" style="display:none;">(expires <span><?php echo $expires_timestamp; ?></span>)</small></p>

    <p>Go through the pipeline inputs below, setting them to the values that you would like.
        When you're done, click <em>Launch</em> and your parameters will be saved.</p>
    <p>If you came to this page by using the <code>nf-core launch</code> command then the changes should be detected.
        If not, the page shown will show a command that you can use to directly launch the workflow.
        For those running on a system with no internet connection, you can copy the parameters JSON to a file
        and use the supplied command to launch.</p>

    <form id="schema_launcher_form" action="" method="post" class="needs-validation" novalidate>

        <input type="hidden" name="cache_id" value="<?php echo $cache_id; ?>">
        <input type="hidden" name="post_content" value="json_schema_launcher_webform">

        <div class="schema-gui-header sticky-top">
            <div class="row align-items-center">
                <div class="col-md-auto">
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="far fa-stream mr-1"></i> <span>Jump to section</span>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <?php
                            foreach($cache['schema']['properties'] as $param_id => $param){
                                if($param['type'] == 'object'){
                                    $html_id = preg_replace('/[^a-z0-9-_]/', '_', preg_replace('/\s+/', '_', strtolower($param_id)));
                                    $hidden_class = 'is_hidden';
                                    foreach($cache['schema']['properties'][$param_id]['properties'] as $child_param_id => $child_param){
                                        if(!isset($child_param['hidden']) || (strtolower($child_param['hidden']) == 'false' || $child_param['hidden'] === false)){
                                            $hidden_class = '';
                                        }
                                    }
                                    $fa_icon = '';
                                    if(isset($param['fa_icon'])){
                                        $fa_icon = '<i class="'.$param['fa_icon'].' fa-fw mr-3 text-secondary"></i>';
                                    }
                                    echo '<a class="dropdown-item '.$hidden_class.' scroll_to_link" href="#'.$html_id.'">'.$fa_icon.$param_id.'</a>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <button class="btn btn-outline-secondary btn-show-hidden-fields" title="Parameters that do not typically need to be altered for a normal run are hidden by default" data-toggle="tooltip" data-delay="500">
                        <span class="is_not_hidden"><i class="fas fa-eye-slash mr-1"></i> Show hidden params</span>
                        <span class="is_hidden"><i class="fas fa-eye mr-1"></i> Hide hidden params</span>
                    </button>
                </div>
                <div class="col d-none d-lg-block">
                    <span id="progress_section" class="text-muted">Nextflow command-line flags</span>
                    <div class="progress" style="height: 2px;">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary btn-launch"><i class="fad fa-rocket-launch mr-1"></i> Launch</button>
                </div>
            </div>
        </div>

        <?php
        foreach($cache['schema']['properties'] as $param_id => $param){
            if($param['type'] == 'object'){
                $html_id = preg_replace('/[^a-z0-9-_]/', '_', preg_replace('/\s+/', '_', strtolower($param_id)));
                $hidden_class = 'is_hidden';
                $child_parameters = '';
                foreach($cache['schema']['properties'][$param_id]['properties'] as $child_param_id => $child_param){
                    $child_parameters .= build_form_param($child_param_id, $child_param, @in_array($child_param_id, $cache['schema']['properties'][$param_id]['required']));
                    if(!isset($child_param['hidden']) || (strtolower($child_param['hidden']) == 'false' || $child_param['hidden'] === false)){
                        $hidden_class = '';
                    }
                }
                $fa_icon = '';
                if(isset($param['fa_icon'])){
                    $fa_icon = '<i class="'.$param['fa_icon'].' fa-fw mr-3"></i>';
                }
                $description = '';
                if(isset($param['description'])){
                    $description = '<p class="form-text">'.$param['description'].'</p>';
                }
                $helptext = '';
                if(isset($param['help_text'])){
                    $helptext = '<small class="form-text text-muted">'.$param['help_text'].'</small>';
                }
                if(strlen($child_parameters) > 0){
                    echo '
                    <fieldset class="'.$hidden_class.'" id="'.$html_id.'">
                        <div class="card">
                            <legend class="h2 card-header">'.$fa_icon.$param_id.'</legend>
                            <div class="card-body">
                                '.$description.$helptext.$child_parameters.'
                            </div>
                        </div>
                    </fieldset>';
                }
            } else {
                echo build_form_param($param_id, $param, @in_array($param_id, $cache['schema']['required']));
            }
        }
        ?>
        <div class="mt-5 text-center">
            <button type="submit" class="btn btn-lg btn-primary  btn-launch" data-target="#schema-finished">
                <i class="fad fa-rocket-launch"></i> Launch workflow
            </button>
            <p class="small text-danger mt-2 validation-warning" style="display: none;">Please fix validation errors before launching.</p>
        </div>
    </form>

    <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" id="form_validation_error_toast">
        <div class="toast-header">
            <strong class="mr-auto text-danger">Validation error</strong>
            <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="toast-body">
            <p>There was a problem validating some of your parameters:</p>
            <ul id="validation_fail_list"></ul>
        </div>
    </div>

<?php } // if $cache

include('../includes/footer.php');
