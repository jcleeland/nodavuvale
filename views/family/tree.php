<!-- Link to Canvg library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/canvg/3.0.7/umd.min.js"></script>

<?php

// Check if the user is logged in and their role
$is_admin = ($auth->isLoggedIn() && $auth->getUserRole() === 'admin');

if(isset($_POST['changeTreeSettings'])) {
    $treeSettings['nodeSize'] = $_POST['treeSize'];
    $treeSettings['generationsShown'] = $_POST['generationsShown'];
    $treeSettings['colorScheme'] = $_POST['colorScheme'];
    $treeSettings['rootId'] = $_POST['treeRootId'];
    $_SESSION['rootId'] = $_POST['treeRootId'];
    $_SESSION['treeSettings'] = $treeSettings;
}

$defaultTreeSettings=[
    'nodeSize'=>1, //This is the spacing between generations
    'generationsShown'=>'all', //This is the number of generations shown
    'colorScheme'=>'gender', //This is the color scheme
    'rootId'=>Web::getRootId() //This is the root individual
];

if(isset($_GET['view']) && $_GET['view'] == 'default') {
    $_SESSION['treeSettings'] = $defaultTreeSettings;
    $treeSettings = $defaultTreeSettings;
    $_SESSION['rootId'] = Web::getRootId();
}

if(isset($_GET['root_id']) && !isset($_POST['changeTreeSettings'])) { //Override other settings if this is in the query string
    $_SESSION['rootId'] = $_GET['root_id'];
}

if(isset($_SESSION['treeSettings'])) {
    //Fill out any empty treesettings with the defaults
    foreach($defaultTreeSettings as $key=>$value) {
        if(!isset($_SESSION['treeSettings'][$key])) {
            $_SESSION['treeSettings'][$key]=$value;
        }
    }
    $treeSettings = $_SESSION['treeSettings'];
} else {
    $treeSettings = $defaultTreeSettings;
    $_SESSION['treeSettings'] = $treeSettings;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['treeWizardAction'])) {
    $selectedRootId = isset($_POST['treeWizardRootId']) ? (int) $_POST['treeWizardRootId'] : 0;
    if ($selectedRootId <= 0) {
        $selectedRootId = Web::getRootId();
    }
    $_SESSION['rootId'] = $selectedRootId;
    if (!isset($_SESSION['treeSettings'])) {
        $_SESSION['treeSettings'] = $defaultTreeSettings;
    }
    $_SESSION['treeSettings']['rootId'] = $selectedRootId;
    $_SESSION['treeWizardCompleted'] = true;
    echo '<script>window.location.href = "?to=family/tree";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=?to=family/tree"></noscript>';
    return;
}

if (isset($_GET['wizard']) && $_GET['wizard'] === 'restart') {
    unset($_SESSION['treeWizardCompleted']);
    header("Location: ?to=family/tree");
    exit;
}

$showWizard = empty($_SESSION['treeWizardCompleted']);

//Handle form submission for updating individuals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_individual') {
    include("helpers/update_individual.php");
}

// $individuals is now gathered in the index.php file so it's available everywhere

$relationships = $db->fetchAll("SELECT * FROM relationships");

/*
// Not sure what this is for, I suspect it is redundant 
$quickindividuallist = $db->fetchAll("SELECT id, first_names, last_name FROM individuals ORDER BY last_name, first_names");
$quicklist=array();
foreach($quickindividuallist as $individualperson) {
$quicklist[$individualperson['id']] = $individualperson;
} 
*/

$defaultRootId = Web::getRootId();
$rootId=isset($_SESSION['rootId']) ? (int) $_SESSION['rootId'] : $defaultRootId;
$treeSettings['rootId'] = $rootId;
$_SESSION['treeSettings']['rootId'] = $rootId;


// Build data for the tree wizard
$individualLookup = [];
$formatIndividualName = static function (array $person = null): string {
    if (!$person) {
        return 'Unknown';
    }
    $first = trim((string) ($person['first_names'] ?? $person['first_name'] ?? ''));
    $last = trim((string) ($person['last_name'] ?? ''));
    $name = trim($first . ' ' . $last);
    if ($name === '') {
        $name = trim((string) ($person['display_name'] ?? 'Unnamed'));
    }
    return $name === '' ? 'Unnamed' : $name;
};
foreach ($individuals as $individualRow) {
    $individualLookup[(int) $individualRow['id']] = $individualRow;
}
$parentToChildren = [];
foreach ($relationships as $relation) {
    if (strtolower((string) ($relation['relationship_type'] ?? '')) !== 'child') {
        continue;
    }
    $parentId = (int) ($relation['individual_id_1'] ?? 0);
    $childId = (int) ($relation['individual_id_2'] ?? 0);
    if ($parentId <= 0 || $childId <= 0) {
        continue;
    }
    $parentToChildren[$parentId][] = $childId;
}
$generationAssignments = [];
$descendantsByGeneration = [];
$namesMap = [];
$queue = new SplQueue();
$visited = [];
if ($defaultRootId > 0) {
    $queue->enqueue([$defaultRootId, 1]);
    $visited[$defaultRootId] = true;
}
while (!$queue->isEmpty()) {
    [$currentId, $generation] = $queue->dequeue();
    $generationAssignments[$currentId] = $generation;
    $namesMap[$currentId] = $formatIndividualName($individualLookup[$currentId] ?? null);
    $descendantsByGeneration[$generation][] = [
        'id'   => $currentId,
        'name' => $namesMap[$currentId],
    ];
    if (!empty($parentToChildren[$currentId])) {
        $nextGeneration = $generation + 1;
        foreach ($parentToChildren[$currentId] as $childId) {
            if (isset($visited[$childId])) {
                continue;
            }
            $visited[$childId] = true;
            $queue->enqueue([$childId, $nextGeneration]);
        }
    }
}
ksort($descendantsByGeneration);
foreach ($descendantsByGeneration as &$generationList) {
    usort($generationList, static function ($a, $b) {
        return strcasecmp((string) $a['name'], (string) $b['name']);
    });
}
unset($generationList);
$treeWizardData = [
    'defaultRootId' => $defaultRootId,
    'defaultRootName' => $formatIndividualName($individualLookup[$defaultRootId] ?? null),
    'currentRootId' => $rootId,
    'currentRootName' => $formatIndividualName($individualLookup[$rootId] ?? null),
    'generations' => $descendantsByGeneration,
    'children' => $parentToChildren,
    'names' => $namesMap,
    'generationMap' => $generationAssignments,
];
$treeWizardJson = json_encode($treeWizardData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);


include("helpers/quickedit.php");

include("helpers/add_relationship.php");

$treeStats = [];
$tree_data = Utils::buildTreeData($rootId, $individuals, $relationships, $_SESSION['treeSettings'], $treeStats);


?>
    <div id="findOnTree" class="modal">
        <div class="modal-content w-3/4 min-w-xs max-h-screen my-5">
            <div class="modal-header">
                <span id="findOnTreeClose" class="close-story-btn" onclick="document.getElementById('findOnTree').style.display='none';">&times;</span>
                <h2 id="findOnTreeTitle" class="text-xl font-bold mb-4 text-center">Find someone on the tree</h2>
            </div>
            <div class="modal-body">
                <label for='lookuptree_display'>Find someone</label><input type='text' placeholder='Find someone on the tree' id='lookuptree_name' name='lookuptree_name' class='w-full border rounded-lg p-2 mb-2' oninput='showSuggestions(this.value)'><div id='lookuptree_suggestions' class='autocomplete-suggestions'></div></div>
                <script type='text/javascript'>

                    function showSuggestions(value) {
                        const suggestionsContainer = document.getElementById('lookuptree_suggestions');
                        suggestionsContainer.innerHTML = '';
                        if (value.length === 0) {
                            return;
                        }

                        const filteredIndividuals = individuals.filter(ind => ind.name.toLowerCase().includes(value.toLowerCase()));
                        filteredIndividuals.forEach(ind => {
                            const suggestion = document.createElement('div');
                            suggestion.className = 'autocomplete-suggestion';
                            suggestion.innerHTML = ind.name;
                            suggestion.onclick = () => selectSuggestion(ind);
                            suggestionsContainer.appendChild(suggestion);
                        });
                    }

                    function selectSuggestion(individual) {
                        const input = document.getElementById('lookuptree_name');

                        // Create a temporary DOM element to parse the HTML
                        const tempElement = document.createElement('div');
                        tempElement.innerHTML = individual.name;
                        const textContent = tempElement.textContent || tempElement.innerText || '';

                        // Assign the text content to the input value
                        input.value = textContent;
                        
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'lookuptree';
                        hiddenInput.value = individual.id;
                        input.parentNode.appendChild(hiddenInput);
                        document.getElementById('lookuptree_suggestions').innerHTML = '';
                        window.location.href = '?to=family/tree&zoom=' + individual.id;
                    }
                    </script>
                <br />&nbsp;<br />
            </div>
        </div>
    </div>

    <div id="TheFamilyTreeDescription" class="modal">
        <div class="modal-content w-3/4 min-w-sm h-4/5 max-h-screen my-5">
            <div class="modal-header">
                <span id="TheFamilyTreeDescriptionClose" class="close-story-btn" onclick="document.getElementById('TheFamilyTreeDescription').style.display = 'none';">&times;</span>
                <h2 id="TheFamilyTreeDescriptionTitle" class="text-xl font-bold mb-4 text-center">Using The Family Tree</h2>
            </div>
            <div class="modal-body overflow-y-hidden overflow-y-scroll" style="height: 88%">
                <p>
                    This is the family tree, by default showing the descendants of Soli and Leonard. 
                    You can click on any individual's picture or name to view their information. 
                    You can also search for an individual by clicking the magnifying glass icon in the top right corner.
                </p>
                &nbsp;
                <p>
                    Each person has their own card, showing their name, birth and death dates, and a picture if available.<br />
                    At the bottom of their card are three buttons:
                    <ul class="list-disc pl-5">
                        <li>📝 to edit their information, 
                        <li>🔗 to add a relationship to them, 
                        <li>➜ to set them as the 'top' person in the tree.
                    </ul>
                </p>
                &nbsp;
                <p>
                    <b>FAQs</b>
                    <ul class='list-disc pl-5'>
                        <li><b>Something is wrong on the tree</b><br />
                        This is one of the reasons we have this site! You can fix it up.<br />
                        If you <b><i>know for sure</i></b> that something is wrong, and you know the correction, you can edit that family individual and correct it.<br />
                        On the other hand, if you <i>think</i> something is wrong, or your family has a different story, you can create a discussion about that person, and we 
                        can all work together to figure out the truth. Or, alternatively, if the truth can't be found, we can have multiple stories about that person.
                        <li><b>Can I add more people to the tree?</b><br />
                        Yes. Absolutely. If you know of someone who should be on the tree, you can add them.<br />The trick is to find someone they are related to, visit their "individual" page, and then add them as a spouse, a parent or a child.
                        <li><b>Can I add a sibling to someone?</b><br />Yes you can, but siblings are a little different. You can't add a sibling directly. You need to add the sibling to the parent, as a child of the same parent to another sibling.
                        <li><b>I'm not sure I want information about me to be visible on this page</b><br />
                        That's fine. Although the privacy feature isn't yet complete, this will be available soon. You'll be able to decide how much and which information is visible to others.
                        <li><b>Something isn't working, or it should work better, or I think I had a great idea!!</b><br />
                        Email <a href='mailto:jason@cleeland.org'>Jason</a>! He's the admin and developer of this site. He's always looking for ways to make it better, and he's always looking for feedback.
                        

                    </ul>
                </p>
            </div>
        </div>

</div>
    

<div id="treeWizardOverlay" class="tree-wizard-overlay <?= $showWizard ? '' : 'hidden' ?>">
    <div class="tree-wizard-modal">
        <button type="button" class="tree-wizard-close<?= $showWizard ? ' hidden' : '' ?>" id="treeWizardClose" aria-label="Close wizard">&times;</button>
        <h2 class="wizard-step-title" id="treeWizardTitle">Family Tree Wizard</h2>
        <div id="treeWizardBody"></div>
    </div>
</div>
<form id="treeWizardForm" method="post" class="hidden">
    <input type="hidden" name="treeWizardAction" value="set_root">
    <input type="hidden" name="treeWizardRootId" id="treeWizardRootId" value="">
</form>
<script>
    window.addEventListener('DOMContentLoaded', function () {
        var wizardData = <?= $treeWizardJson ?>;
        var wizardOverlay = document.getElementById('treeWizardOverlay');
        var wizardBody = document.getElementById('treeWizardBody');
        var wizardTitle = document.getElementById('treeWizardTitle');
        var wizardForm = document.getElementById('treeWizardForm');
        var wizardRootInput = document.getElementById('treeWizardRootId');
        var wizardOpenButton = document.getElementById('treeWizardOpen');
        var wizardCloseButton = document.getElementById('treeWizardClose');
        var treeSection = document.getElementById('treeSection');
        var wizardInitial = <?= $showWizard ? 'true' : 'false' ?>;
        var generationKeys = Object.keys(wizardData.generations || {}).map(Number).sort(function (a, b) { return a - b; });
        var namesMap = wizardData.names || {};
        var childrenLookup = wizardData.children || {};
        var generationMap = wizardData.generationMap || {};

        var state = {
            isInitial: wizardInitial,
            mode: null,
            generation: null,
            currentRootId: null,
            path: []
        };

        function escapeHtml(str) {
            if (typeof str !== 'string') {
                return '';
            }
            return str.replace(/[&<>"'`]/g, function (c) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                    '`': '&#96;'
                }[c];
            });
        }

        function setTitle(text) {
            wizardTitle.textContent = text;
        }

        function pathSummaryHtml() {
            if (!state.path.length) {
                return '';
            }
            var summaryIds = state.path.slice();
            if (wizardData.defaultRootId && summaryIds[0] !== wizardData.defaultRootId) {
                summaryIds.unshift(wizardData.defaultRootId);
            }
            var parts = summaryIds.map(function (id) {
                return escapeHtml(namesMap[id] || ('#' + id));
            });
            return '<div class="wizard-path"><strong>Selected path:</strong> ' + parts.join(' <span aria-hidden="true">&rsaquo;</span> ') + '</div>';
        }
        function submitWizard(rootId) {
            if (!rootId) {
                rootId = wizardData.defaultRootId || wizardData.currentRootId || 0;
            }
            wizardRootInput.value = rootId;
            wizardForm.submit();
        }

        function renderModeStep() {
            setTitle('How would you like to explore the tree?');
            wizardBody.innerHTML = '<div class="wizard-option-grid">\
                <button type="button" class="wizard-option" id="wizardModeFull">\
                    <strong>Display full tree</strong><br>\
                    <span class="text-sm text-slate-600">Show the complete tree for ' + escapeHtml(wizardData.defaultRootName || 'the root ancestor') + '.</span>\
                </button>\
                <button type="button" class="wizard-option" id="wizardModeCustom">\
                    <strong>Select a descendancy path</strong><br>\
                    <span class="text-sm text-slate-600">Focus on a branch by choosing a generation and descendants.</span>\
                </button>\
            </div>';
            document.getElementById('wizardModeFull').addEventListener('click', function () {
                submitWizard(wizardData.defaultRootId || wizardData.currentRootId || 0);
            });
            document.getElementById('wizardModeCustom').addEventListener('click', function () {
                state.mode = 'custom';
                renderGenerationStep();
            });
        }

        function renderGenerationStep() {
            state.path = [];
            state.currentRootId = null;
            setTitle('Select a generation');
            var buttons = generationKeys.map(function (gen) {
                var labelName = '';
                var generationList = wizardData.generations[gen] || [];
                if (generationList.length === 1) {
                    labelName = ' (' + escapeHtml(generationList[0].name) + ')';
                }
                return '<button type="button" class="wizard-option" data-generation="' + gen + '">\
                            <strong>Generation ' + gen + '</strong>' + (labelName ? '<br><span class="text-sm text-slate-600">' + labelName + '</span>' : '') + '\
                        </button>';
            }).join('');
            wizardBody.innerHTML = '<p class="wizard-path text-sm text-slate-500">Root ancestor: ' + escapeHtml(wizardData.defaultRootName || 'Unknown') + '</p>\
                <div class="wizard-option-grid">' + buttons + '</div>' + (state.mode ? '<button type="button" class="wizard-option wizard-back" id="wizardBackToMode">Back</button>' : '');
            wizardBody.querySelectorAll('[data-generation]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var gen = parseInt(this.getAttribute('data-generation'), 10);
                    state.generation = gen;
                    renderIndividualStep();
                });
            });
            var backBtn = document.getElementById('wizardBackToMode');
            if (backBtn) {
                backBtn.addEventListener('click', renderModeStep);
            }
        }

        function renderIndividualStep() {
            state.path = [];
            state.currentRootId = null;
            setTitle('Choose someone in generation ' + state.generation);
            var people = wizardData.generations[state.generation] || [];
            wizardBody.innerHTML =
                '<input type="search" class="wizard-search w-full border rounded-lg p-2" placeholder="Search generation ' + state.generation + '..." id="wizardIndividualSearch">' +
                '<div id="wizardIndividualList" class="wizard-option-grid"></div>' +
                '<button type="button" class="wizard-option wizard-back" id="wizardBackToGeneration">Back</button>';
            var listEl = document.getElementById('wizardIndividualList');
            function renderList(filter) {
                listEl.innerHTML = '';
                var normalized = (filter || '').trim().toLowerCase();
                people.filter(function (person) {
                    if (!normalized) {
                        return true;
                    }
                    return (person.name || '').toLowerCase().includes(normalized);
                }).forEach(function (person) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'wizard-option';
                    btn.textContent = person.name;
                    btn.addEventListener('click', function () {
                        state.currentRootId = person.id;
                        state.generation = generationMap[person.id] || state.generation;
                        state.path = [person.id];
                        renderBranchOptions();
                    });
                    listEl.appendChild(btn);
                });
            }
            renderList('');
            document.getElementById('wizardIndividualSearch').addEventListener('input', function () {
                renderList(this.value);
            });
            document.getElementById('wizardBackToGeneration').addEventListener('click', renderGenerationStep);
        }

        function renderBranchOptions() {
            setTitle('Refine the descendancy path');
            wizardBody.innerHTML = pathSummaryHtml() + '<div class="wizard-option-grid">\
                <button type="button" class="wizard-option" id="wizardShowAll">\
                    <strong>Show all descendants & generations</strong><br>\
                    <span class="text-sm text-slate-600">View everyone descended from this person.</span>\
                </button>\
                <button type="button" class="wizard-option" id="wizardNarrow">\
                    <strong>Narrow down the descendancy path</strong><br>\
                    <span class="text-sm text-slate-600">Choose someone from the next generation to focus further.</span>\
                </button>\
            </div>\
            <button type="button" class="wizard-option wizard-back" id="wizardBackToIndividuals">Back</button>';
            document.getElementById('wizardShowAll').addEventListener('click', function () {
                submitWizard(state.currentRootId);
            });
            document.getElementById('wizardNarrow').addEventListener('click', function () {
                renderChildSelection();
            });
            document.getElementById('wizardBackToIndividuals').addEventListener('click', renderIndividualStep);
        }

        function renderChildSelection() {
            var children = childrenLookup[state.currentRootId] || [];
            children = children.filter(function (id) {
                return !!namesMap[id];
            });
            if (!children.length) {
                wizardBody.innerHTML = pathSummaryHtml() + '<p class="text-sm text-red-600 mb-3">No further descendants were found for this person.</p>\
                    <div class="wizard-option-grid">\
                        <button type="button" class="wizard-option" id="wizardFinishNoChildren"><strong>Show tree with current selection</strong></button>\
                    </div>\
                    <button type="button" class="wizard-option wizard-back" id="wizardBackToBranch">Back</button>';
                document.getElementById('wizardFinishNoChildren').addEventListener('click', function () {
                    submitWizard(state.currentRootId);
                });
                document.getElementById('wizardBackToBranch').addEventListener('click', renderBranchOptions);
                return;
            }
            var nextGeneration = (generationMap[state.currentRootId] || 1) + 1;
            setTitle('Pick someone in generation ' + nextGeneration);
            var options = children.map(function (id) {
                return '<button type="button" class="wizard-option" data-child="' + id + '">\
                            <strong>' + escapeHtml(namesMap[id] || ('#' + id)) + '</strong>\
                        </button>';
            }).join('');
            wizardBody.innerHTML = pathSummaryHtml() + '<div class="wizard-option-grid">' + options + '</div>\
                <button type="button" class="wizard-option wizard-back" id="wizardBackToBranch">Back</button>';
            wizardBody.querySelectorAll('[data-child]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var childId = parseInt(this.getAttribute('data-child'), 10);
                    state.currentRootId = childId;
                    state.generation = generationMap[childId] || (state.generation + 1);
                    state.path.push(childId);
                    renderBranchOptions();
                });
            });
            document.getElementById('wizardBackToBranch').addEventListener('click', renderBranchOptions);
        }

        function openWizard() {
            state.isInitial = false;
            if (wizardOverlay) {
                wizardOverlay.classList.remove('hidden');
            }
            if (wizardCloseButton) {
                wizardCloseButton.classList.remove('hidden');
            }
            if (treeSection) {
                treeSection.style.display = 'none';
            }
            state.mode = null;
            state.generation = null;
            state.currentRootId = null;
            state.path = [];
            renderModeStep();
        }

        function closeWizard() {
            if (state.isInitial) {
                return;
            }
            if (wizardOverlay) {
                wizardOverlay.classList.add('hidden');
            }
            if (treeSection) {
                treeSection.style.display = '';
            }
        }

        if (wizardOpenButton) {
            wizardOpenButton.addEventListener('click', function () {
                openWizard();
            });
        }

        if (wizardCloseButton) {
            wizardCloseButton.addEventListener('click', function () {
                closeWizard();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeWizard();
            }
        });

        if (wizardInitial) {
            if (treeSection) {
                treeSection.style.display = 'none';
            }
            renderModeStep();
        }
    });
</script>

<style>
    .tree-wizard-overlay {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.65);
        z-index: 3000;
    }
    .tree-wizard-overlay.hidden {
        display: none;
    }
    .tree-wizard-modal {
        width: min(90vw, 560px);
        max-height: 90vh;
        overflow: auto;
        background: #ffffff;
        border-radius: 1rem;
        box-shadow: 0 25px 45px rgba(15, 23, 42, 0.2);
        padding: 1.5rem;
        position: relative;
    }
    .tree-wizard-close {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        font-size: 1.25rem;
        cursor: pointer;
        color: #1f2937;
        background: none;
        border: none;
    }
    .wizard-step-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: #1f2937;
    }
    .wizard-option-grid {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .wizard-option {
        display: block;
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid rgba(15, 23, 42, 0.12);
        border-radius: 0.75rem;
        background: #f8fafc;
        text-align: left;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
    }
    .wizard-option:hover {
        background: #e2e8f0;
        border-color: rgba(15, 23, 42, 0.25);
    }
    .wizard-search {
        margin-bottom: 0.75rem;
    }
    .wizard-path {
        margin-bottom: 0.75rem;
        font-size: 0.9rem;
        color: #475569;
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    .wizard-back {
        margin-top: 1rem;
    }
</style>


<section id="treeSection" class="mx-auto py-12 px-4 sm:px-6 lg:px-8" <?= $showWizard ? 'style="display:none;"' : '' ?>>
    <button class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" 
            title="How to use the family tree" onclick="showHelp()">
        <i class="fas fa-question-circle"></i>
    </button>
    <button class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" 
            title="View insights" id="treeInsightsToggle">
        <i class="fas fa-chart-pie"></i>
    </button>
    <button class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" 
            title="Find person in tree" onclick="viewTreeSearch()">
        <i class="fas fa-search"></i>
    </button>
    <button class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right hidden" 
            title="Print" id="exportTree" >
        <i class="fas fa-print"></i>
    </button>
    <button class="hidden bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" 
            onclick="navigator.clipboard.writeText(JSON.stringify(tree))">
        &#128203;
    </button>
    <button class="hidden add-new-btn bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" 
            title="Add new individual">
        <i class="fas fa-plus"></i>
    </button>
    <button class="treesettings bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" 
            title="Tree settings" onclick="document.getElementById('treeSettingsModal').style.display='block';">
        <i class="fas fa-cog"></i>
    </button>
    <button class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" 
            title="Open tree wizard" type="button" id="treeWizardOpen">
        <i class="fas fa-magic"></i><span class="sr-only">Open tree wizard</span>
    </button>
    <button class="bg-deep-green hover:bg-deep-green-dark text-white px-4 py-2 ml-1 rounded-lg float-right" 
            title="Reset the tree to the default view" onclick="window.location.href='?to=family/tree&view=default'">
        <i class="fas fa-home"></i>
    </button>

    <h1 class="text-3xl font-bold mb-4">Family Tree</h1>

    <form method='post' id='treeSettingsModal' class='hidden' action='?to=family/tree'>
    <div class="grid grid-cols-5 gap-4 bg-burnt-orange-800 nv-bg-opacity-10 border rounded mb-1 px-2 pb-1 text-sm overflow-hidden overflow-x-scroll">
        <!--<div class="modal-content w-3/4 min-w-sm h-4/5 max-h-screen my-5">
            <div class="modal-header">
                <span id="treeSettingsModalClose" class="close-story-btn" onclick="document.getElementById('treeSettingsModal').style.display = 'none';">&times;</span>
                <h2 id="treeSettingsModalTitle" class="text-xl font-bold mb-4 text-center">Tree Settings</h2>
            </div>
            <div class="modal-body overflow-y-hidden overflow-y-scroll" style="max-height: 88%">-->
            <!-- A div that allows 5 columns of settings to be displayed -->
                <div class="mt-2 mb-1">
                    <label for="treeRootId" class="block text-gray-500 text-xs overflow-hidden">Root&nbsp;Individual</label>
                    <select id="treeRootId" name="treeRootId" class="w-full px-4 py-2 border rounded-lg">
                        <?php
                            foreach($individuals as $individual) {
                                echo "<option value='".$individual['id']."'";
                                if($individual['id'] == $rootId) {
                                    echo " selected";
                                }
                                echo ">".str_replace("_", "&nbsp;", $individual['first_names'])." ".$individual['last_name']."</option>";
                            }
                        ?>
                    </select>
                </div>
                <div class="mt-2 mb-1">
                    <label for="treeNodeSize" class="block text-gray-500 text-xs overflow-hidden">Tree&nbsp;Spacings</label>
                    <select id="treeNodeSize" name="treeSize" class="w-full px-4 py-2 border rounded-lg">
                        <option value="1">Normal</option>
                        <option value="0">Compressed</option>
                    </select>
                </div>
                <div class="mt-2 mb-1">
                    <label for="treeGenerationsShown" class="block text-gray-500 text-xs overflow-hidden">Generations&nbsp;Shown</label>
                    <select id="treeGenerationsShown" name="generationsShown" class="w-full px-4 py-2 border rounded-lg">
                        <option value="all">All</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                        <option value="6">6</option>
                    </select>
                </div>
                <div class="mt-2 mb-1">
                    <label for="treeColorScheme" class="block text-gray-500 text-xs overflow-hidden">Colours&nbsp;Shown<br /></label>
                    <select id="treeColorScheme" name="colorScheme" class="w-full px-4 py-2 border rounded-lg">
                        <option value="gender">Gender</option>
                        <option value="firstGenLines">1st Generation Lines</option>
                    </select>
                </div>
                <div class="justify-right text-right whitespace-nowrap mt-2 mb-1">
                    &nbsp;<br />
                    <button type="submit" class="bg-blue-500 text-white px-2 sm:px-4 py-2 rounded" name="changeTreeSettings">
                        <i class="fas fa-check"></i>
                    </button>
                    <button type="button" class="bg-gray-500 text-white px-1 sm:px-4 py-2 rounded mr-1" onclick="document.getElementById('treeSettingsModal').style.display='none';">
                        <i class='fas fa-eye-slash'></i>
                    </button>
                </div>
    </div>
    </form>
    <script>
        // match the tree settings modal options to the current settings
        document.getElementById('treeNodeSize').value = '<?= $treeSettings['nodeSize'] ?>';
        document.getElementById('treeGenerationsShown').value = '<?= $treeSettings['generationsShown'] ?>';
        document.getElementById('treeColorScheme').value = '<?= $treeSettings['colorScheme'] ?>';
        document.getElementById('treeRootId').value = '<?= $treeSettings['rootId'] ?>';
    </script>    

    <?php if (!empty($treeStats)): ?>
    <div id="tree-insights-panel" class="hidden fixed inset-0 z-[2100] flex items-center justify-center p-4 sm:p-6 bg-slate-900/40">
        <div class="relative max-w-5xl w-full max-h-[85vh] h-full overflow-y-auto bg-white rounded-3xl shadow-2xl border border-slate-200/70">
            <div class="sticky top-0 z-10 flex justify-end bg-white rounded-t-3xl px-6 sm:px-8 pt-5 pb-3 border-b border-slate-200/60">
                <button type="button" id="treeInsightsClose" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="px-6 pb-8 pt-2 sm:px-8">
                <h2 class="text-2xl font-semibold text-slate-800 mb-6">Tree Insights</h2>
                <div class="grid gap-6 md:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/70 shadow-sm p-6">
                        <h3 class="text-xs uppercase tracking-wide text-slate-500 font-semibold mb-3">Individuals in view</h3>
                        <p class="text-4xl font-bold text-slate-800 mb-4"><?= number_format($treeStats['total'] ?? 0) ?></p>
                        <ul class="text-sm text-slate-600 space-y-2">
                            <li class="flex justify-between"><span class="font-medium text-slate-700">Female</span><span><?= number_format($treeStats['by_gender']['female'] ?? 0) ?></span></li>
                            <li class="flex justify-between"><span class="font-medium text-slate-700">Male</span><span><?= number_format($treeStats['by_gender']['male'] ?? 0) ?></span></li>
                            <li class="flex justify-between"><span class="font-medium text-slate-700">Other / Unstated</span><span><?= number_format($treeStats['by_gender']['other'] ?? 0) ?></span></li>
                        </ul>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 border border-emerald-200/80 shadow-sm p-6">
                        <h3 class="text-xs uppercase tracking-wide text-emerald-700 font-semibold mb-3">Estimated living relatives</h3>
                        <p class="text-4xl font-bold text-emerald-600 mb-3"><?= number_format($treeStats['living'] ?? 0) ?></p>
                        <p class="text-xs text-emerald-900/80 leading-snug">
                            Assumes people without a recorded death year have passed if they are 100+ years old or have descendants spanning at least three generations.
                        </p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/70 shadow-sm p-6">
                        <h3 class="text-xs uppercase tracking-wide text-slate-500 font-semibold mb-3">Most common first names</h3>
                        <?php if (!empty($treeStats['top_first_names'])): ?>
                        <div class="max-h-56 overflow-y-auto pr-2">
                            <ol class="space-y-2 text-sm text-slate-600 list-decimal list-inside">
                                <?php foreach ($treeStats['top_first_names'] as $index => $entry): ?>
                                    <li>
                                        <span class="font-medium text-slate-700"><?= htmlspecialchars($entry['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-slate-500"> (<?= number_format($entry['count']) ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                        <?php else: ?>
                            <p class="text-sm text-slate-600">Not enough information yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Family Tree Display -->
    <div id="family-tree" class="familytree bg-burnt-orange-800 nv-bg-opacity-10 border rounded"></div>

    <script>
        var tree = <?= $tree_data; ?>;
        // Get the width of the current page
        var windowWidth = window.innerWidth;
        // Get the height of the window
        var windowHeight = window.innerHeight-200;
        tree=dTree.init(tree, {
            target: "#family-tree",
            debug: false,
            width: windowWidth,
            height: windowHeight,
            nodeWidth: 100,
            styles: {
                node: 'node',
                linage: 'linage',
                marriage: 'marriage',
                text: 'nodeText',
            },
            callbacks: {
                nodeClick: function(name, extra) {
                    //console.log(name);
                },
                textRenderer: function (name, extra, textClass) {
                    return "<div style='height: 170px'>"+name+"</div>";
                }
            }
        });

var familyTreeContainer = document.getElementById('family-tree');
var insightsPanel = document.getElementById('tree-insights-panel');
var insightsToggleButton = document.getElementById('treeInsightsToggle');
var insightsCloseButton = document.getElementById('treeInsightsClose');

if (insightsToggleButton && insightsPanel) {
    insightsToggleButton.addEventListener('click', function () {
        var willShow = insightsPanel.classList.toggle('hidden') === false;
        this.setAttribute('aria-pressed', willShow ? 'true' : 'false');
        if (willShow) {
            this.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
            this.innerHTML = "<i class='fas fa-chart-pie'></i>";
        } else {
            this.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
            this.classList.add('bg-blue-500', 'hover:bg-blue-700');
            this.innerHTML = "<i class='fas fa-chart-pie'></i>";
        }
    });
}

if (insightsCloseButton && insightsPanel) {
    insightsCloseButton.addEventListener('click', function () {
        insightsPanel.classList.add('hidden');
        if (insightsToggleButton) {
            insightsToggleButton.setAttribute('aria-pressed', 'false');
            insightsToggleButton.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
        }
    });
}

window.addEventListener('resize', function () {
            if (tree && tree.zoomToFit) {
                clearTimeout(window.__treeResizeTimer);
                window.__treeResizeTimer = setTimeout(function () {
                    tree.zoomToFit(0);
                }, 250);
            }
        });

        <?php
            if(isset($_GET['zoom'])) {
        ?>
        window.onload = function() {
            findNodeForIndividualId(<?= $_GET['zoom'] ?>)
                .then(nodeId => {
                    //Now remove the characters "node" from the front of the nodeId
                    nodeId = nodeId.replace("node", "");
                    //Now make sure it's a number not a string
                    nodeId = parseInt(nodeId);
                    console.log('Found node: ' + nodeId);
                    tree.zoomToNode(nodeId, 2, 500);
                    // Delay the highlighting feature by 500ms to ensure it runs after the zoom is complete
                    setTimeout(function() {
                        // Now make the div's parent element briefly grow and then shrink
                        var node = document.getElementById("node" + nodeId);
                        var parent = node.parentNode;
                        parent.style.transition = "all 0.5s";
                        parent.style.transformOrigin = "bottom left";
                        parent.style.transform = "scale(1.1)";
                        setTimeout(function() {
                            parent.style.transform = "scale(1)";
                        }, 600);
                    }, 600);


                })
                .catch(error => {
                    console.error(error);
                });
        };
        <?php } ?>
    </script>






</section>
