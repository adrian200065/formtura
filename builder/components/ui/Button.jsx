import { forwardRef } from 'react';

const Button = forwardRef(({
  children,
  className = '',
  icon: Icon,
  iconOnly = false,
  variant = 'secondary',
  type = 'button',
  ...props
}, ref) => {
  const classes = [
    'formtura-btn',
    `formtura-btn-${variant}`,
    iconOnly ? 'formtura-btn-icon' : '',
    className,
  ].filter(Boolean).join(' ');

  return (
    <button ref={ref} type={type} className={classes} {...props}>
      {Icon && <Icon aria-hidden="true" className="formtura-btn-leading-icon" />}
      <span className={iconOnly ? 'formtura-sr-only' : ''}>{children}</span>
    </button>
  );
});

Button.displayName = 'Button';

export default Button;
