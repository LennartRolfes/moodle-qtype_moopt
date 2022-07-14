define(['core/ajax', 'core/modal_factory', 'core/modal_events', 'core/str'],
function (ajax, ModalFactory, ModalEvents, Strings) {

    var timer;
    var qubaid;
    var isCurrentlyShowingModal = false;

    function checkGradingFinished() {
        ajax.call([
            {
                methodname: 'qtype_moopt_service_retrieve_grading_results',
                args: {qubaid: qubaid},
                done: function (result) {
                    result['estimatedSecondsRemainingForEachQuestion'].forEach(function (estimatedSecondsRemainingForOneQuestion) {
                        let elems = document.getElementsByClassName("estimatedSecondsRemaining_" + estimatedSecondsRemainingForOneQuestion['questionId']);
                            Array.from(elems).forEach(function(div) {
                            if (div.style.display === 'none') {
                                div.style.display = 'block';
                            }
                        });
                        elems = document.getElementsByClassName("estimatedSecondsRemainingValue_" + estimatedSecondsRemainingForOneQuestion['questionId']);
                        Array.from(elems).forEach(function(elem) {
                            elem.innerHTML = estimatedSecondsRemainingForOneQuestion['estimatedSecondsRemaining'];
                        })
                    });
                    if (result['finished']) {
                        showReloadModal();
                    }
                },
                fail: function (errorObject) {
                    console.log(errorObject);
                }
            }
        ]);
    }

    function showReloadModal() {
        if (isCurrentlyShowingModal) {
            return;
        }
        isCurrentlyShowingModal = true;

        var strings = [
        {
            key: 'reloadpage',
            component: 'qtype_moopt'
        },
        {
            key: 'gradeprocessfinished',
            component: 'qtype_moopt'
        },
        {
            key: 'reload',
            component: 'qtype_moopt'
        }];

        Strings.get_strings(strings).then(function (values) {
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: values[0],
                body: values[1]
            }).done(function (modal) {
                modal.setSaveButtonText(values[2]);

                modal.getRoot().on(ModalEvents.save, function () {
                    location.reload(true);
                    isCurrentlyShowingModal = false;
                });

                modal.getRoot().on(ModalEvents.hidden, function () {
                    isCurrentlyShowingModal = false;
                });

                modal.show();
            });
        });
    }

    return {

        init: function (qubaid_param, slot, polling_interval) {
            // Don't show the retry button yet.
            document.querySelectorAll("input[name='redoslot" + slot + "'").forEach(function (elem) {
                elem.remove();
            });

            qubaid = qubaid_param;
            if (typeof timer === 'undefined') {
                timer = setInterval(checkGradingFinished, polling_interval);
            }
        }
    };

});