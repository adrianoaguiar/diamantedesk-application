define(['app'], function(App){

  return App.module('Ticket.Edit', function(Edit, App, Backbone, Marionette, $, _){

    Edit.TicketController = function(id){

      require([
        'modules/Ticket/models/ticket',
        'modules/Ticket/views/edit'], function(Models, EditView){

        App.request("ticket:model", id).done(function(editTicketModel){

          var editTicketView = new Edit.ItemView({
                model: editTicketModel
              }),
              modalEditView = new Edit.ModalView({
                title: 'Edit Ticket ' + editTicketModel.get('branch').key + "-" + editTicketModel.id
              });

          modalEditView.on('show', function(){
            this.$el.modal();
          });

          modalEditView.on('modal:closed', function(){
            App.trigger('ticket:view', editTicketModel.get('id'));
          });

          modalEditView.on('modal:submit', function(data){
            editTicketModel.set(data);
            var attrs = editTicketModel.changedAttributes();
            if(attrs){
              editTicketModel.save(attrs,{
                patch : true,
                success : function(resultModel){
                  App.trigger('ticket:view', resultModel.get('id'));
                  modalEditView.off('modal:closed');
                  modalEditView.$el.modal('hide');
                }
              });
            } else {
              modalEditView.$el.modal('hide');
            }
          });

          App.DialogRegion.show(modalEditView);
          modalEditView.ModalBody.show(editTicketView);

        }).fail(function(){

          var ticketMissingView = new Edit.MissingView();
          App.MainRegion.show(ticketMissingView);

        });

      });

    };

  });

});