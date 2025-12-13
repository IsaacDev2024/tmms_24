<?php
require_once(dirname(__FILE__) . '/../../config.php');

// Incluir la clase base de bloques para Moodle 4.1
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

// Ahora incluir nuestro archivo después de que Moodle esté cargado
require_once(dirname(__FILE__) . '/block_tmms_24.php');

if (!isloggedin()) {
    redirect($CFG->wwwroot . '/login/index.php');
}

$courseid = required_param('cid', PARAM_INT);
$view_results = optional_param('view_results', 0, PARAM_INT);
$scroll = optional_param('scroll', '', PARAM_RAW_TRIMMED);

if ($courseid == SITEID && !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

$PAGE->set_url('/blocks/tmms_24/view.php', array('cid' => $courseid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('test_title', 'block_tmms_24'));
$PAGE->set_heading(get_string('test_title', 'block_tmms_24'));

// Verificar si ya existe información del usuario (sin importar el curso)
$entry = $DB->get_record('tmms_24', array('user' => $USER->id));

// Crear registro si no existe (requerido por save.php que usa responseid)
if (!$entry) {
    $newentry = (object)[
        'user' => $USER->id,
        'course' => $courseid,
        'age' => null,
        'gender' => null,
        'is_completed' => 0,
        'created_at' => time(),
        'updated_at' => time(),
    ];
    $newid = $DB->insert_record('tmms_24', $newentry);
    $entry = $DB->get_record('tmms_24', array('id' => $newid), '*', MUST_EXIST);
} else {
    // Mantener el curso actual (la tabla es única por usuario)
    if ((int)$entry->course !== (int)$courseid) {
        $entry->course = $courseid;
        $entry->updated_at = time();
        $DB->update_record('tmms_24', $entry);
    }
}

echo $OUTPUT->header();
echo "<link rel='stylesheet' href='" . $CFG->wwwroot . "/blocks/tmms_24/styles.css'>";
echo "<div class='block_tmms_24_container'>";

if ($entry && (int)$entry->is_completed === 1 && $view_results) {
    // Mostrar resultados completos
    $responses = [
        $entry->item1, $entry->item2, $entry->item3, $entry->item4, $entry->item5, $entry->item6, $entry->item7, $entry->item8,
        $entry->item9, $entry->item10, $entry->item11, $entry->item12, $entry->item13, $entry->item14, $entry->item15, $entry->item16,
        $entry->item17, $entry->item18, $entry->item19, $entry->item20, $entry->item21, $entry->item22, $entry->item23, $entry->item24
    ];
    
    $scores = TMMS24Facade::calculate_scores($responses);
    $interpretations = TMMS24Facade::get_all_interpretations($scores, $entry->gender);
    
    echo "<div class='tmms-results-container'>";
    echo "<h2>" . get_string('results_title', 'block_tmms_24') . "</h2>";
    
    // Header with student name
    echo '<div class="mb-4">';
    echo '<h4 class="mb-0">' . get_string('results_for', 'block_tmms_24') . ' ' . fullname($USER) . '</h4>';
    echo '</div>';
    
    // Display results summary with cards (like student_results.php)
    echo '<div class="row mb-4">';
    echo '<div class="col-md-4">';
    echo '<div class="card border-primary">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('perception', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-primary">' . $scores['percepcion'] . '/40</h3>';
    echo '<p class="mb-0">' . $interpretations['percepcion'] . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-4">';
    echo '<div class="card border-info">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('comprehension', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-info">' . $scores['comprension'] . '/40</h3>';
    echo '<p class="mb-0">' . $interpretations['comprension'] . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-4">';
    echo '<div class="card border-success">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('regulation', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-success">' . $scores['regulacion'] . '/40</h3>';
    echo '<p class="mb-0">' . $interpretations['regulacion'] . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Test completion info
    echo '<div class="row mb-4">';
    echo '<div class="col-md-6">';
    echo '<strong>' . get_string('date_completed', 'block_tmms_24') . ':</strong> ';
    echo userdate($entry->created_at, get_string('strftimedatetimeshort'));
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<strong>' . get_string('gender', 'block_tmms_24') . ':</strong> ';
    
    // Convert gender code to display string
    $gender_display = '';
    switch($entry->gender) {
        case 'M':
            $gender_display = get_string('gender_male', 'block_tmms_24');
            break;
        case 'F':
            $gender_display = get_string('gender_female', 'block_tmms_24');
            break;
        case 'prefiero_no_decir':
            $gender_display = get_string('gender_prefer_not_say', 'block_tmms_24');
            break;
        default:
            $gender_display = $entry->gender; // fallback
    }
    echo $gender_display;
    echo '</div>';
    echo '</div>';

    // Detailed responses
    echo '<h5>' . get_string('detailed_responses', 'block_tmms_24') . '</h5>';
    
    $dimensions = [
        'perception' => range(1, 8),
        'comprehension' => range(9, 16), 
        'regulation' => range(17, 24)
    ];

    foreach ($dimensions as $dimension => $items) {
        echo '<div class="card mb-3">';
        echo '<div class="card-header">';
        echo '<h6 class="mb-0">' . get_string($dimension, 'block_tmms_24') . '</h6>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        
        foreach ($items as $item_num) {
            $item_key = 'item' . $item_num;
            $response_value = $entry->$item_key;
            
            echo '<tr>';
            echo '<td style="width: 60px;"><strong>' . $item_num . '.</strong></td>';
            echo '<td>' . get_string('item' . $item_num, 'block_tmms_24') . '</td>';
            echo '<td style="width: 100px;" class="text-center">';
            echo '<span class="badge badge-' . ($response_value >= 4 ? 'success' : ($response_value >= 3 ? 'warning' : 'danger')) . '">';
            echo $response_value . '/5';
            echo '</span>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    // Interpretation section
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h5 class="mb-0">' . get_string('results_interpretation', 'block_tmms_24') . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    
    // Display interpretations directly
    echo '<div class="row">';
    echo '<div class="col-md-4"><strong>' . get_string('perception', 'block_tmms_24') . ':</strong><br>' . $interpretations['percepcion'] . '</div>';
    echo '<div class="col-md-4"><strong>' . get_string('comprehension', 'block_tmms_24') . ':</strong><br>' . $interpretations['comprension'] . '</div>';
    echo '<div class="col-md-4"><strong>' . get_string('regulation', 'block_tmms_24') . ':</strong><br>' . $interpretations['regulacion'] . '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    
    // Botones de acción
    echo "<div class='results-actions mt-4'>";
    
    // Solo profesores/administradores pueden descargar resultados
    if (has_capability('block/tmms_24:viewallresults', context_course::instance($courseid))) {
        echo "<a href='" . new moodle_url('/blocks/tmms_24/export.php', array('cid' => $courseid, 'format' => 'csv')) . "' class='btn btn-success'>" . get_string('download_csv', 'block_tmms_24') . "</a> ";
        echo "<a href='" . new moodle_url('/blocks/tmms_24/export.php', array('cid' => $courseid, 'format' => 'json')) . "' class='btn btn-success'>" . get_string('download_json', 'block_tmms_24') . "</a> ";
    }
    
    echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary'>" . get_string('back_to_course', 'block_tmms_24') . "</a>";
    echo "</div>";
    
    // Disclaimer
    echo "<div class='disclaimer'>";
    echo "<p><em>" . get_string('disclaimer', 'block_tmms_24') . "</em></p>";
    echo "<p><small>" . get_string('legal_notice', 'block_tmms_24') . "</small></p>";
    echo "</div>";
    
    echo "</div>";

} else if ($entry && (int)$entry->is_completed === 1 && !$view_results) {
    // Ya completó el test, mostrar opción de ver resultados (NO retomar)
    echo "<div class='test-completed-message'>";
    echo "<h2>" . get_string('test_completed', 'block_tmms_24') . "</h2>";
    echo "<p>" . get_string('test_already_completed', 'block_tmms_24') . "</p>";
    echo "<div class='completed-actions'>";
    echo "<a href='" . new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid, 'view_results' => 1)) . "' class='btn btn-primary'>" . get_string('view_full_results', 'block_tmms_24') . "</a> ";
    echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary'>" . get_string('back_to_course', 'block_tmms_24') . "</a>";
    echo "</div>";
    echo "</div>";

} else {
    // Mostrar formulario del test (nuevo o en progreso)
    echo "<div class='tmms-test-container'>";
    
    // Instrucciones
    echo "<div class='test-instructions'>";
    echo "<h2>" . get_string('instructions_title', 'block_tmms_24') . "</h2>";
    echo "<p>" . get_string('instructions_text', 'block_tmms_24') . "</p>";
    echo "<p>" . get_string('instructions_text2', 'block_tmms_24') . "</p>";
    echo "</div>";
    
    // Formulario
    echo "<form method='POST' action='" . $CFG->wwwroot . "/blocks/tmms_24/save.php' class='tmms-form' id='tmmsForm'>";
    echo "<input type='hidden' name='cid' value='" . $courseid . "'>";
    echo "<input type='hidden' name='responseid' value='" . (int)$entry->id . "'>";
    echo "<input type='hidden' name='sesskey' value='" . sesskey() . "'>";
    
    // Datos demográficos
    echo "<div class='demographics-section'>";
    echo "<h3>" . get_string('demographics', 'block_tmms_24') . "</h3>";
    echo "<div class='form-row'>";
    echo "<div class='form-group'>";
    echo "<label for='age'>" . get_string('age', 'block_tmms_24') . " *</label>";
    $agevalue = (!empty($entry->age) ? (int)$entry->age : '');
    echo "<input type='number' id='age' name='age' class='form-control' min='10' max='100' value='" . s($agevalue) . "'>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label for='gender'>" . get_string('gender', 'block_tmms_24') . " *</label>";
    $gendervalue = (!empty($entry->gender) ? $entry->gender : '');
    echo "<select id='gender' name='gender' class='form-control'>";
    echo "<option value=''>Seleccione...</option>";
    echo "<option value='M'" . ($gendervalue === 'M' ? " selected" : "") . ">" . get_string('gender_male', 'block_tmms_24') . "</option>";
    echo "<option value='F'" . ($gendervalue === 'F' ? " selected" : "") . ">" . get_string('gender_female', 'block_tmms_24') . "</option>";
    echo "<option value='prefiero_no_decir'" . ($gendervalue === 'prefiero_no_decir' ? " selected" : "") . ">" . get_string('gender_prefer_not_say', 'block_tmms_24') . "</option>";
    echo "</select>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Cuestionario
    echo "<div class='questionnaire-section'>";
    echo "<h3>" . get_string('questionnaire', 'block_tmms_24') . "</h3>";

    // Etiquetas de escala (sin números), para mostrar en cada pregunta.
    $scale_labels = [];
    for ($i = 1; $i <= 5; $i++) {
        $raw = get_string('scale_' . $i, 'block_tmms_24');
        $label = preg_replace('/^\s*\d+\s*[=:\-–—\.]+\s*/u', '', $raw);
        $scale_labels[$i] = trim($label);
    }
    
    $items = TMMS24Facade::get_tmms24_items();
    foreach ($items as $number => $text) {
        echo "<div class='question-item' data-item='" . $number . "' id='question-" . $number . "'>";
        echo "<div class='question-header'>";
        echo "<span class='question-text'>" . $text . "</span>";
        echo "</div>";
        echo "<div class='likert-scale'>";
        for ($i = 1; $i <= 5; $i++) {
            $itemfield = 'item' . $number;
            $checked = (isset($entry->$itemfield) && (int)$entry->$itemfield === $i) ? " checked" : "";
            echo "<label class='likert-option'>";
            echo "<input type='radio' name='item" . $number . "' value='" . $i . "'" . $checked . ">";
            echo "<span class='likert-label'>" . s($scale_labels[$i]) . "</span>";
            echo "</label>";
        }
        echo "</div>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Botón de envío
    echo "<div class='form-actions'>";
    echo "<div id='finishButtonContainer'>";
    echo "<button type='submit' class='btn btn-primary btn-lg' id='submitBtn'>" . get_string('submit_test', 'block_tmms_24') . "</button>";
    echo "</div>";
    echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary btn-lg'>" . get_string('back_to_course', 'block_tmms_24') . "</a>";
    echo "</div>";
    
    echo "</form>";
    echo "</div>";
}

echo "</div>";

// JavaScript para validación y UX
echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('tmmsForm');
    const responseIdInput = form ? form.querySelector('input[name=\"responseid\"]') : null;
    const sesskeyInput = form ? form.querySelector('input[name=\"sesskey\"]') : null;
    const courseIdInput = form ? form.querySelector('input[name=\"cid\"]') : null;
    const scrollParam = " . json_encode($scroll) . ";
    
    if (form) {
        let formAttempted = false;
        let autosaveTimer = null;
        let pendingAutosave = {};
        
        // Event listeners para los radios
        const radios = form.querySelectorAll('input[type=\"radio\"]');
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                queueAutosave({ [this.name]: this.value });
                
                // Remover clase de error visual si se responde
                if (formAttempted) {
                    const itemNumber = this.name.replace('item', '');
                    const questionDiv = document.getElementById('question-' + itemNumber);
                    if (questionDiv) {
                        questionDiv.classList.remove('unanswered');
                        questionDiv.classList.remove('scroll-highlight');
                    }
                }
            });
        });
        
        // Validación del formulario
        form.addEventListener('submit', function(e) {
            formAttempted = true;
            
            if (!validateForm()) {
                e.preventDefault();
                
                // Marcar visualmente demografía
                const ageInput = document.getElementById('age');
                const genderSelect = document.getElementById('gender');
                
                if (!ageInput.value) {
                    ageInput.classList.add('invalid');
                } else {
                    ageInput.classList.remove('invalid');
                }
                
                if (!genderSelect.value) {
                    genderSelect.classList.add('invalid');
                } else {
                    genderSelect.classList.remove('invalid');
                }
                
                // Marcar visualmente las preguntas sin responder
                for (let i = 1; i <= 24; i++) {
                    const checked = form.querySelector('input[name=\"item' + i + '\"]:checked');
                    const questionDiv = document.getElementById('question-' + i);
                    
                    if (!checked && questionDiv) {
                        questionDiv.classList.add('unanswered');
                    } else if (questionDiv) {
                        questionDiv.classList.remove('unanswered');
                    }
                }
                
                // Scroll al primer campo inválido
                const firstInvalidDemo = document.querySelector('#age.invalid, #gender.invalid');
                const firstUnanswered = document.querySelector('.question-item.unanswered');
                
                if (firstInvalidDemo) {
                    firstInvalidDemo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (firstUnanswered) {
                    firstUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                return false;
            }
        });
        
        // Guardado automático (BD) de datos demográficos
        const ageInput = document.getElementById('age');
        const genderSelect = document.getElementById('gender');
        
        if (ageInput) {
            ageInput.addEventListener('input', function() {
                queueAutosave({ age: this.value });
                if (formAttempted && this.value) {
                    this.classList.remove('invalid');
                }
            });
        }
        if (genderSelect) {
            genderSelect.addEventListener('change', function() {
                queueAutosave({ gender: this.value });
                if (formAttempted && this.value) {
                    this.classList.remove('invalid');
                }
            });
        }

        // Resaltado verde al reanudar desde el bloque (?scroll=...)
        if (scrollParam) {
            if (scrollParam === 'finish') {
                const finish = document.getElementById('finishButtonContainer');
                if (finish) {
                    finish.classList.add('scroll-highlight');
                    finish.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => {
                        finish.classList.remove('scroll-highlight');
                    }, 5000);
                }
            } else {
                const n = parseInt(scrollParam, 10);
                if (!isNaN(n)) {
                    const q = document.getElementById('question-' + n);
                    if (q) {
                        q.classList.add('scroll-highlight');
                        q.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => {
                            q.classList.remove('scroll-highlight');
                        }, 5000);
                    }
                }
            }
        }

        function queueAutosave(partialData) {
            pendingAutosave = Object.assign(pendingAutosave, partialData);
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
            }
            autosaveTimer = setTimeout(() => {
                const payload = Object.assign({}, pendingAutosave);
                pendingAutosave = {};
                doAutosave(payload);
            }, 400);
        }

        async function doAutosave(payload) {
            if (!responseIdInput || !sesskeyInput || !courseIdInput) {
                return;
            }

            const params = new URLSearchParams();
            params.set('ajax', '1');
            params.set('auto_save', '1');
            params.set('cid', courseIdInput.value);
            params.set('responseid', responseIdInput.value);
            params.set('sesskey', sesskeyInput.value);

            Object.keys(payload).forEach(key => {
                if (payload[key] !== undefined && payload[key] !== null) {
                    params.set(key, payload[key]);
                }
            });

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString(),
                    credentials: 'same-origin'
                });

                const text = await res.text();
                try {
                    const json = JSON.parse(text);
                    if (!json || json.success !== true) {
                        // Silencioso: no interrumpimos al usuario
                    }
                } catch (e) {
                    // Silencioso: si algo imprime HTML, no rompemos la UX
                }
            } catch (e) {
                // Silencioso: si no hay red, el usuario puede continuar
            }
        }
    }
    
    function validateForm() {
        let isValid = true;
        
        // Validar datos demográficos
        const age = document.getElementById('age');
        const gender = document.getElementById('gender');
        
        if (!age.value) {
            isValid = false;
        }
        if (!gender.value) {
            isValid = false;
        }
        
        // Validar todos los ítems
        for (let i = 1; i <= 24; i++) {
            const checked = form.querySelector('input[name=\"item' + i + '\"]:checked');
            if (!checked) {
                isValid = false;
            }
        }
        
        return isValid;
    }
    
});
</script>";

echo $OUTPUT->footer();
?>
