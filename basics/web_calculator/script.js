const display = document.getElementById('currentInput');
const numberButtons =  document.querySelectorAll('.number');
const operatorButtons = document.querySelectorAll('.operator');
const funcButtons = document.querySelectorAll('.func');
const dotButton = document.querySelector('.dot');
const clearHistoryBtn = document.getElementById('clearHistoryBtn');
const equalsBtn = document.getElementById('equals');

function updateFontSize() {
    const length = display.value.length;
    if (length <= 18){
        display.style.fontSize = "32px";
    }
    else if (length > 18 && length <= 25) {
        display.style.fontSize = "24px";
    } else {
        display.style.fontSize = "18px";
    }
    display.scrollLeft = display.scrollWidth;
}

function addToHistory(expression, result) {
    const historyList = document.getElementById('historyList');
    const historyItem = document.createElement('div');
    historyItem.className = 'history-item';
    historyItem.textContent = `${expression} = ${result}`;

    const emptyMsg = historyList.querySelector('.history-empty');
    if (emptyMsg){
        emptyMsg.remove();
    }

    historyList.prepend(historyItem);

    if(historyList.children.length > 10) {
        historyList.lastElementChild.remove();
    }
}

numberButtons.forEach(button => {
    button.addEventListener('click', () => {
        display.value += button.dataset.num;
        updateFontSize();
    });
});

dotButton.addEventListener('click', () => {
    const currentValue = display.value;
    const parts = currentValue.split(/[+\-*/^]/);
    const lastPart = parts[parts.length - 1];

    if (!lastPart.includes('.')){
        if (lastPart === ''){
            display.value += '0.';
        }
        else {
            display.value += '.'
        }
        updateFontSize();
    }
    
});

operatorButtons.forEach(button => {
    button.addEventListener('click', () => {
        const op = button.dataset.op;
        const lastChar = display.value.slice(-1);
        const preLastChar = display.value.slice(-2, -1);
        const operators = ['+', '-', '*', '/', '^', 'sqrt'];

        if (display.value === '') {
            if (op === '-' || op === 'sqrt'){
                display.value += op;
                updateFontSize();
            }
            return;
        }

        if (operators.includes(lastChar)){
            if(op === '-'){
                if(lastChar !== '-'){
                    display.value += op;
                }
                else if (!operators.includes(preLastChar)){
                    display.value = display.value.slice(0, -1);
                    display.value += '+';
                } 
            }
            else if (!operators.includes(preLastChar)){
                display.value = display.value.slice(0, -1);
                display.value += op;
            }
        }
        else{
            display.value += op;
        }

        updateFontSize();
    });
});

funcButtons.forEach(button => {
    button.addEventListener('click', () => {
        const action = button.dataset.action;

        switch (action){
            case 'backspace':
                if (display.value.slice(-1) === 't'){
                    display.value = display.value.slice(0, -4);
                }
                else{
                    display.value = display.value.slice(0, -1);
                }
                break;
            case 'c':
                display.value = '';
                break;
            case 'ac':
                display.value = '';
                break;
            default:
                break;
        }

        updateFontSize();
    });
});

equalsBtn.addEventListener('click', () => {
    const expression = display.value;
    if (!expression) return;

    const formData = new FormData();
    formData.append('expr', expression);

    fetch('calc.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success'){
            addToHistory(expression, data.result);
            display.value = data.result;
            updateFontSize();
        }
        else {
            alert(data.message);
        }
    })
    .catch(err => console.error("Ошибка запроса:", err));
});

clearHistoryBtn.addEventListener('click', () => {
    const historyList = document.getElementById('historyList');
    historyList.innerHTML = '<div class="history-empty">Нет операций</div>';
})