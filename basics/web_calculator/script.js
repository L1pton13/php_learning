const display = document.getElementById('currentInput');
const numberButtons =  document.querySelectorAll('.number');
const operatorButtons = document.querySelectorAll('.operator');
const funcButtons = document.querySelectorAll('.func');
const dotButton = document.querySelector('.dot');

numberButtons.forEach(button => {
    button.addEventListener('click', () => {
        display.value += button.dataset.num;
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
    }
    
});
operatorButtons.forEach(button => {
    button.addEventListener('click', () => {
        const op = button.dataset.op;
        const lastChar = display.value.slice(-1);
        const preLastChar = display.value.slice(-2, -1);
        const operators = ['+', '-', '*', '/', '^'];

        if (display.value === '') {
            if (op === '-'){
                display.value += op;
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
    });
});

funcButtons.forEach(button => {
    button.addEventListener('click', () => {
        const action = button.dataset.action;

        switch (action){
            case 'backspace':
                display.value = display.value.slice(0, -1);
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
    });
});

