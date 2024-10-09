/**
 * Calls the getAjax function to get the data from the server.
 * All ajax calls are routed through /ajax.php and must include a method and then a keyed array of data
 *
 * @param {*} method 
 * @param {*} data 
 * @returns 
 */
async function getAjax(method, data) {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            method: method,
            data: data
        })
    });
    if (!response.ok) {
        throw new Error('Network response was not ok');
    }
    return await response.json();
}

// Make a modal draggable
function makeModalDraggable(modal, header) {
    let isDragging = false;
    let offsetX, offsetY;

    header.addEventListener('mousedown', function(e) {
        isDragging = true;
        offsetX = e.clientX - modal.offsetLeft;
        offsetY = e.clientY - modal.offsetTop;
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    function onMouseMove(e) {
        if (isDragging) {
            modal.style.left = e.clientX - offsetX + 'px';
            modal.style.top = e.clientY - offsetY + 'px';
        }
    }

    function onMouseUp() {
        isDragging = false;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    }
}