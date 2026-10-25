'use strict';
/*
 * categorias.js — diálogo "Cadastre nova Categoria" da listagem de produtos (public/admin/produtos/index.php).
 * Ao abrir, busca o token CSRF em /api/categorias/opcoes; ao salvar, envia para /api/categorias (POST).
 * Back-end ainda não implementado. Quando salva, dispara o evento "categoria:criada" (ouvido pelo produtos.js).
 */
(() => {
  const $=id=>document.getElementById(id),dialog=$('category-dialog'),form=$('category-form'),name=$('category-name');
  let csrf='',ready=false,saving=false,generation=0,optionsController;
  // Mensagens do diálogo: aviso geral (com "Tentar novamente") e erro do campo nome.
  function message(text,retry=false){$('category-message-text').textContent=text;$('category-message').hidden=!text;$('retry-category').hidden=!retry;}
  function fieldError(text){$('category-error').textContent=text;$('category-error').hidden=!text;if(text){name.setAttribute('aria-invalid','true');name.focus();}else name.removeAttribute('aria-invalid');}
  function saveState(){$('save-category').disabled=!ready||saving;name.disabled=saving;$('cancel-category').disabled=saving;$('retry-category').disabled=saving;}
  // Prepara o salvamento: token CSRF e confirmação de que existe usuário responsável.
  async function loadOptions(){const current=++generation;optionsController?.abort();const controller=optionsController=new AbortController(),timeout=setTimeout(()=>controller.abort(),15000);ready=false;saveState();$('retry-category').disabled=true;try{const response=await fetch('/api/categorias/opcoes',{cache:'no-store',signal:controller.signal});if(!response.ok)throw Error();const data=await response.json();if(current!==generation||!dialog.open)return;if(!data.csrf_token)throw Error();csrf=data.csrf_token;ready=data.responsavel_configurado===true;message(ready?'':'Solicite ao administrador a configuração de um usuário responsável para habilitar o salvamento.',!ready);}catch(_){if(current===generation&&dialog.open)message('Não foi possível preparar o cadastro. Verifique a conexão e tente novamente.',true);}finally{clearTimeout(timeout);if(current===generation){$('retry-category').disabled=false;saveState();}}}
  // Abrir, cancelar e fechar o diálogo (não fecha enquanto está salvando).
  $('open-category').addEventListener('click',()=>{form.reset();fieldError('');message('');csrf='';ready=false;dialog.showModal();document.body.classList.add('category-open');name.focus();loadOptions();});
  $('cancel-category').addEventListener('click',()=>{if(!saving)dialog.close();});
  dialog.addEventListener('cancel',event=>{if(saving)event.preventDefault();});
  // Mantém o foco do teclado (Tab) dentro do diálogo.
  dialog.addEventListener('keydown',event=>{if(event.key!=='Tab')return;const controls=[...dialog.querySelectorAll('input,button')].filter(el=>!el.disabled&&el.getClientRects().length);if(!controls.length){event.preventDefault();return;}const first=controls[0],last=controls[controls.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}});
  // Fecha ao clicar fora da caixa (somente se o clique começou e terminou fora).
  let pointerOutside=false;
  dialog.addEventListener('pointerdown',event=>{const r=dialog.getBoundingClientRect();pointerOutside=event.target===dialog&&(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom);});
  dialog.addEventListener('click',event=>{const r=dialog.getBoundingClientRect();if(pointerOutside&&!saving&&event.target===dialog&&(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom))dialog.close();pointerOutside=false;});
  dialog.addEventListener('close',()=>{generation++;optionsController?.abort();document.body.classList.remove('category-open');$('open-category').focus();});
  name.addEventListener('input',()=>fieldError(''));
  $('retry-category').addEventListener('click',loadOptions);
  // Salvar: valida o nome (obrigatório, até 100 caracteres) e envia para a API.
  form.addEventListener('submit',async event=>{event.preventDefault();if(saving||!ready)return;const value=name.value.trim();if(!value){fieldError('Informe o nome da categoria.');return;}if(value.length>100){fieldError('Use no máximo 100 caracteres.');return;}fieldError('');message('');saving=true;saveState();form.setAttribute('aria-busy','true');$('save-category').textContent='Salvando…';const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),20000);try{const response=await fetch('/api/categorias',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({nome:value}),signal:controller.signal}),result=await response.json();if(!response.ok){if(result.fields?.nome)fieldError(result.fields.nome);else message(result.error||'Não foi possível salvar a categoria.',response.status===403);if(response.status===403){ready=false;csrf='';}return;}if(!Number.isInteger(result.id)||typeof result.nome!=='string')throw Error();dialog.close();document.dispatchEvent(new CustomEvent('categoria:criada',{detail:result}));}catch(_){message('Não foi possível confirmar o cadastro. Consulte as categorias antes de tentar novamente para evitar duplicidade.');}finally{clearTimeout(timeout);saving=false;form.setAttribute('aria-busy','false');$('save-category').textContent='Salvar';saveState();if(!$('category-error').hidden)name.focus();}});
})();
