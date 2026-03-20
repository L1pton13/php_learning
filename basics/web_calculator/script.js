const display = document.getElementById('currentInput');
const numberButtons =  document.querySelectorAll('.number');
const operatorButtons = document.querySelectorAll('.operator');

numberButtons.forEach(button => {
    button.addEventListener('click', () => {
        display.value += button.dataset.num;
    });
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

