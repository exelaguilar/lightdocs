const form=document.querySelector('[data-export-form]');
const acknowledgement=document.querySelector('[data-private-ack]');
function updateProfile(){const profile=form?.elements.profile?.value;const privateProfile=profile==='private';if(acknowledgement)acknowledgement.hidden=!privateProfile;if(!privateProfile&&form?.elements.acknowledge_secrets)form.elements.acknowledge_secrets.checked=false;}
form?.addEventListener('change',updateProfile);
form?.addEventListener('submit',event=>{if(form.elements.profile.value==='private'&&!form.elements.acknowledge_secrets.checked){event.preventDefault();alert('Acknowledge the private export warning before building.');return;}const button=form.querySelector('button[type="submit"]');button.disabled=true;button.textContent='Building export...';});
updateProfile();
